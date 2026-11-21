<?php

declare(strict_types=1);

namespace VeloForum\Core;

final class Autoloader
{
    public static function register(string $srcRoot): void
    {
        spl_autoload_register(static function (string $class) use ($srcRoot): void {
            $prefix = 'VeloForum\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $srcRoot . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
