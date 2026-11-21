<?php

declare(strict_types=1);

return static function (string $rootPath): array {
    $envFile = $rootPath . '/.env';
    $envExists = is_file($envFile) && is_readable($envFile);
    if ($envExists) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $idx => $line) {
            if ($idx === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            }
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            $val = trim(substr($trimmed, $pos + 1));
            $val = trim($val, "\"'");
            if ($key !== '' && !array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
            }
        }
    }

    return [
        'env_exists' => $envExists,
        'app' => [
            'name' => $_ENV['APP_NAME'] ?? 'VeloForum',
            'url'  => rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/'),
            'env'  => $_ENV['APP_ENV'] ?? 'production',
        ],
        'target' => [
            'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? 'veloforum',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => 'utf8mb4',
        ],
        'ic5' => [
            'host'     => $_ENV['IC5_DB_HOST'] ?? '127.0.0.1',
            'port'     => (int) ($_ENV['IC5_DB_PORT'] ?? 3306),
            'database' => $_ENV['IC5_DB_DATABASE'] ?? 'invision_ic5',
            'username' => $_ENV['IC5_DB_USERNAME'] ?? 'root',
            'password' => $_ENV['IC5_DB_PASSWORD'] ?? '',
            'prefix'   => $_ENV['IC5_DB_PREFIX'] ?? '',
            'charset'  => 'utf8mb4',
        ],
    ];
};
