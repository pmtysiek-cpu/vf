#!/usr/bin/env php
<?php

declare(strict_types=1);

use VeloForum\Core\Autoloader;
use VeloForum\Core\Database;
use VeloForum\Migration\Ic5Migrator;

$root = dirname(__DIR__);
require_once $root . '/src/Core/Autoloader.php';
Autoloader::register($root . '/src');

$configLoader = require $root . '/config/database.php';
$config = $configLoader($root);

$execute = in_array('--execute', $argv, true);
$dryRun  = in_array('--dry-run', $argv, true) || !$execute;

echo "=== VeloForum PHP 8.3 — Migrator Invision Community 5 ===\n";

try {
    $sourcePdo = Database::connect($config['ic5']);
    $targetPdo = Database::connect($config['target']);
} catch (\Throwable $e) {
    fwrite(STDERR, "Błąd połączenia z bazą danych: {$e->getMessage()}\n");
    exit(1);
}

$migrator = new Ic5Migrator($sourcePdo, $targetPdo, (string) ($config['ic5']['prefix'] ?? ''));
$audit = $migrator->audit();

echo sprintf(
    "Wykryto w bazie IC5 -> Grupy: %d | Użytkownicy: %d | Działy/Podkategorie: %d | Tematy: %d | Posty: %d\n",
    $audit['groups'],
    $audit['members'],
    $audit['forums'],
    $audit['topics'],
    $audit['posts']
);

if ($dryRun) {
    echo "Tryb --dry-run zakończony. Uruchom z flagą --execute aby zapisać dane do VeloForum.\n";
    exit(0);
}

$report = $migrator->run(static function (string $message): void {
    echo "[MIGRATOR] {$message}\n";
});

echo "\nMigracja zakończona pomyślnie!\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
