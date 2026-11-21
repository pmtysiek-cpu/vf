<?php

declare(strict_types=1);

namespace VeloForum\Service;

use PDO;

/**
 * Obsługuje uwierzytelnianie użytkowników z zachowaniem 100% zgodności haseł
 * zmigrowanych z Invision Community 5 / IPS 4 ($2y$ bcrypt) oraz starszych IPS 3 (MD5+salt).
 * Zawiera ochronę przed brute-force oraz cichy rehash do PASSWORD_ARGON2ID.
 */
final class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 8;
    private const LOCKOUT_SECONDS = 300;

    public function __construct(private readonly PDO $db)
    {
    }

    public function isRateLimited(): bool
    {
        $attempts = $_SESSION['_vf_login_attempts'] ?? [];
        $cutoff = time() - self::LOCKOUT_SECONDS;
        $recent = array_values(array_filter($attempts, static fn (int $ts): bool => $ts >= $cutoff));
        $_SESSION['_vf_login_attempts'] = $recent;

        return count($recent) >= self::MAX_LOGIN_ATTEMPTS;
    }

    public function attemptLogin(string $loginOrEmail, string $plainPassword): ?array
    {
        if ($this->isRateLimited()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT m.*, g.name AS group_name, g.color_hex, g.prefix_html AS group_prefix, g.suffix_html AS group_suffix,
                    g.is_admin, g.is_moderator, g.can_post
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.username = :login OR m.email = :email
             LIMIT 1'
        );
        $stmt->execute([
            'login' => trim($loginOrEmail),
            'email' => mb_strtolower(trim($loginOrEmail)),
        ]);
        $member = $stmt->fetch();

        if (!$member || (int) $member['is_banned'] === 1 || !$this->verifyMemberPassword($plainPassword, $member)) {
            $_SESSION['_vf_login_attempts'][] = time();
            return null;
        }

        unset($_SESSION['_vf_login_attempts']);
        $this->upgradeHashIfNeeded((int) $member['id'], $plainPassword, $member);

        $this->db->prepare('UPDATE vf_members SET last_activity_at = :now WHERE id = :id')
            ->execute(['now' => time(), 'id' => (int) $member['id']]);

        $member = $this->enrichMemberGroups($member);
        session_regenerate_id(true);
        $_SESSION['vf_member_id'] = (int) $member['id'];
        $_SESSION['_vf_last_ping'] = time();

        return $member;
    }

    /**
     * @return array{ok: bool, member_id?: int, error?: string}
     */
    public function register(string $username, string $email, string $plainPassword): array
    {
        $cleanUser = trim($username);
        $cleanEmail = mb_strtolower(trim($email));

        $existsStmt = $this->db->prepare(
            'SELECT id FROM vf_members WHERE username = :u OR email = :e LIMIT 1'
        );
        $existsStmt->execute(['u' => $cleanUser, 'e' => $cleanEmail]);
        if ($existsStmt->fetch()) {
            return ['ok' => false, 'error' => 'Użytkownik o takiej nazwie lub adresie e-mail już istnieje.'];
        }

        $useArgon = defined('PASSWORD_ARGON2ID');
        $hash = $useArgon
            ? password_hash($plainPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 1,
            ])
            : password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $slug = $this->slugify($cleanUser);
        $stmt = $this->db->prepare(
            'INSERT INTO vf_members
             (username, slug, email, password_hash, legacy_algo, primary_group_id, joined_at, last_activity_at)
             VALUES (:username, :slug, :email, :hash, :algo, 3, :joined, :activity)'
        );
        $now = time();
        $stmt->execute([
            'username' => $cleanUser,
            'slug'     => $slug,
            'email'    => $cleanEmail,
            'hash'     => $hash,
            'algo'     => $useArgon ? 'argon2id' : 'bcrypt',
            'joined'   => $now,
            'activity' => $now,
        ]);

        return ['ok' => true, 'member_id' => (int) $this->db->lastInsertId()];
    }

    public function currentMember(): ?array
    {
        $id = $_SESSION['vf_member_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT m.*, g.name AS group_name, g.color_hex, g.prefix_html AS group_prefix, g.suffix_html AS group_suffix,
                    g.is_admin, g.is_moderator, g.can_post
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.id = :id AND m.is_banned = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $member = $stmt->fetch();

        if (!$member) {
            return null;
        }

        $now = time();
        $lastPing = (int) ($_SESSION['_vf_last_ping'] ?? 0);
        if (($now - $lastPing) >= 60) {
            try {
                $this->db->prepare('UPDATE vf_members SET last_activity_at = :now WHERE id = :id')
                    ->execute(['now' => $now, 'id' => (int) $member['id']]);
            } catch (\Throwable) {
                // Ignorujemy błąd zapisu aktywności
            }
            $_SESSION['_vf_last_ping'] = $now;
            $member['last_activity_at'] = $now;
        }

        return $this->enrichMemberGroups($member);
    }

    private function enrichMemberGroups(array $member): array
    {
        $ids = [(int) ($member['primary_group_id'] ?? 3)];
        $rawSec = $member['secondary_group_ids'] ?? '[]';
        $sec = is_array($rawSec) ? $rawSec : json_decode((string) $rawSec, true);
        if (is_array($sec)) {
            foreach ($sec as $gid) {
                $id = (int) $gid;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        $member['all_group_ids'] = $ids;

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $gStmt = $this->db->prepare(
                "SELECT is_admin, is_moderator, can_post,
                        COALESCE(can_set_profile_cover, 0) AS can_set_profile_cover,
                        COALESCE(can_set_profile_music, 0) AS can_set_profile_music,
                        COALESCE(can_use_chat, 1) AS can_use_chat,
                        COALESCE(can_moderate_chat, 0) AS can_moderate_chat
                 FROM vf_groups WHERE id IN ({$placeholders})"
            );
            $gStmt->execute($ids);
            $member['can_set_profile_cover'] = $member['can_set_profile_cover'] ?? 0;
            $member['can_set_profile_music'] = $member['can_set_profile_music'] ?? 0;
            $member['can_use_chat'] = $member['can_use_chat'] ?? 0;
            $member['can_moderate_chat'] = $member['can_moderate_chat'] ?? 0;
            foreach ($gStmt->fetchAll() as $g) {
                if ((int) ($g['is_admin'] ?? 0) === 1) {
                    $member['is_admin'] = 1;
                    $member['can_set_profile_cover'] = 1;
                    $member['can_set_profile_music'] = 1;
                    $member['can_use_chat'] = 1;
                    $member['can_moderate_chat'] = 1;
                }
                if ((int) ($g['is_moderator'] ?? 0) === 1) {
                    $member['is_moderator'] = 1;
                    $member['can_moderate_chat'] = 1;
                }
                if ((int) ($g['can_post'] ?? 0) === 1) {
                    $member['can_post'] = 1;
                }
                if ((int) ($g['can_set_profile_cover'] ?? 0) === 1) {
                    $member['can_set_profile_cover'] = 1;
                }
                if ((int) ($g['can_set_profile_music'] ?? 0) === 1) {
                    $member['can_set_profile_music'] = 1;
                }
                if ((int) ($g['can_use_chat'] ?? 0) === 1) {
                    $member['can_use_chat'] = 1;
                }
                if ((int) ($g['can_moderate_chat'] ?? 0) === 1) {
                    $member['can_moderate_chat'] = 1;
                }
            }
        }

        return $member;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    private function verifyMemberPassword(string $plainPassword, array $member): bool
    {
        $storedHash = (string) $member['password_hash'];
        $algo = (string) ($member['legacy_algo'] ?? 'bcrypt');

        if ($algo === 'bcrypt' || $algo === 'argon2id' || str_starts_with($storedHash, '$2')) {
            return password_verify($plainPassword, $storedHash);
        }

        if ($algo === 'ips3_md5') {
            $salt = (string) ($member['legacy_salt'] ?? '');
            $expected = md5(md5($salt) . md5($plainPassword));
            return hash_equals($storedHash, $expected);
        }

        return false;
    }

    private function upgradeHashIfNeeded(int $memberId, string $plainPassword, array $member): void
    {
        $storedHash = (string) $member['password_hash'];
        $useArgon = defined('PASSWORD_ARGON2ID');
        $targetAlgo = $useArgon ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $needsUpgrade = ($member['legacy_algo'] ?? '') === 'ips3_md5'
            || password_needs_rehash($storedHash, $targetAlgo);

        if (!$needsUpgrade) {
            return;
        }

        $newHash = $useArgon
            ? password_hash($plainPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 1,
            ])
            : password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->db->prepare(
            'UPDATE vf_members
             SET password_hash = :hash, legacy_salt = NULL, legacy_algo = :algo
             WHERE id = :id'
        )->execute([
            'hash' => $newHash,
            'algo' => $useArgon ? 'argon2id' : 'bcrypt',
            'id'   => $memberId,
        ]);
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'user';
        return trim($slug, '-') ?: 'user';
    }
}
