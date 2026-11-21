<?php

declare(strict_types=1);

namespace VeloForum\Core;

final class Csrf
{
    private const SESSION_KEY = '_vf_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $submitted): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        if ($expected === '' || $submitted === null || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }
}
