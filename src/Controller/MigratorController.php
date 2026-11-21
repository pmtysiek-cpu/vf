<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use PDO;
use VeloForum\Core\Csrf;
use VeloForum\Core\Database;
use VeloForum\Core\View;
use VeloForum\Migration\Ic5Migrator;
use VeloForum\Service\AuthService;

final class MigratorController
{
    public function __construct(
        private readonly View $view,
        private readonly PDO $targetDb,
        private readonly array $ic5Config,
        private readonly AuthService $auth
    ) {
    }

    public function index(): void
    {
        if (!$this->isAuthorized()) {
            http_response_code(403);
            $this->view->render('login', [
                'pageTitle'   => 'Wymagane uprawnienia administratora',
                'error'       => 'Dostęp do kreatora migracji jest chroniony. Zaloguj się na konto administratora.',
                'currentUser' => $this->auth->currentMember(),
            ]);
            return;
        }

        $this->view->render('migrator', [
            'pageTitle'   => 'Migrator Invision Community 5',
            'ic5Config'   => $this->ic5Config,
            'report'      => null,
            'error'       => null,
            'currentUser' => $this->auth->currentMember(),
        ]);
    }

    public function run(): void
    {
        if (!$this->isAuthorized()) {
            http_response_code(403);
            exit('Brak uprawnień administratora.');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $cfg = [
            'host'     => trim((string) ($_POST['host'] ?? $this->ic5Config['host'])),
            'port'     => (int) ($_POST['port'] ?? $this->ic5Config['port']),
            'database' => trim((string) ($_POST['database'] ?? $this->ic5Config['database'])),
            'username' => trim((string) ($_POST['username'] ?? $this->ic5Config['username'])),
            'password' => (string) ($_POST['password'] ?? $this->ic5Config['password']),
            'prefix'   => trim((string) ($_POST['prefix'] ?? '')),
            'charset'  => 'utf8mb4',
        ];

        @set_time_limit(0);

        $logErr = static function (string $msg): void {
            error_log('[VeloForum Migrator] ' . $msg);
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_dir($logDir) && is_writable($logDir)) {
                @file_put_contents($logDir . '/veloforum.log', '[' . date('Y-m-d H:i:s') . '] [Migrator] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        };

        try {
            $sourceDb = Database::connect($cfg);
        } catch (\Throwable $connEx) {
            $msg = $connEx->getMessage();
            $logErr('Connect error: ' . $msg);
            $hint = 'Nie udało się połączyć z bazą źródłową IC5: ' . $msg;
            if (stripos($msg, 'Access denied') !== false || str_contains($msg, '[1045]')) {
                $hint = 'Błąd logowania do bazy IC5 (1045): nieprawidłowy użytkownik lub hasło. (' . $msg . ')';
            } elseif (stripos($msg, 'Unknown database') !== false || str_contains($msg, '[1049]')) {
                $hint = 'Baza IC5 wskazana w polu „Nazwa bazy IC5” nie istnieje (1049). (' . $msg . ')';
            } elseif (stripos($msg, 'Connection refused') !== false || stripos($msg, 'timed out') !== false || str_contains($msg, '[2002]')) {
                $hint = 'Nie można połączyć z hostem MySQL IC5 (2002): sprawdź host (np. localhost / 127.0.0.1 lub IP serwera) oraz port. (' . $msg . ')';
            }
            $this->view->render('migrator', [
                'pageTitle'   => 'Migrator Invision Community 5',
                'ic5Config'   => $cfg,
                'report'      => null,
                'error'       => $hint,
                'currentUser' => $this->auth->currentMember(),
            ]);
            return;
        }

        try {
            $migrator = new Ic5Migrator($sourceDb, $this->targetDb, $cfg['prefix']);
            $report = $migrator->run();
            $this->view->render('migrator', [
                'pageTitle'   => 'Migrator Invision Community 5',
                'ic5Config'   => $cfg,
                'report'      => $report,
                'error'       => null,
                'currentUser' => $this->auth->currentMember(),
            ]);
        } catch (\Throwable $e) {
            $logErr('Run error: ' . $e->getMessage());
            $this->view->render('migrator', [
                'pageTitle'   => 'Migrator Invision Community 5',
                'ic5Config'   => $cfg,
                'report'      => null,
                'error'       => 'Połączono z bazą IC5, ale wystąpił problem podczas migracji: ' . $e->getMessage(),
                'currentUser' => $this->auth->currentMember(),
            ]);
        }
    }

    /**
     * Dostęp do kreatora WWW możliwy tylko podczas pierwszej instalacji (0 kont w bazie)
     * lub dla zalogowanego administratora.
     */
    private function isAuthorized(): bool
    {
        $user = $this->auth->currentMember();
        if ($user && (int) ($user['is_admin'] ?? 0) === 1) {
            return true;
        }
        $memberCount = (int) $this->targetDb->query('SELECT COUNT(*) FROM vf_members')->fetchColumn();
        return $memberCount === 0;
    }
}
