<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Service\AuthService;

final class AuthController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthService $auth
    ) {
    }

    public function loginForm(): void
    {
        $this->view->render('login', [
            'pageTitle'   => 'Logowanie',
            'error'       => null,
            'currentUser' => $this->auth->currentMember(),
        ]);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        if ($this->auth->isRateLimited()) {
            $this->view->render('login', [
                'pageTitle'   => 'Logowanie',
                'error'       => 'Zbyt wiele nieudanych prób logowania. Odczekaj 5 minut.',
                'currentUser' => null,
            ]);
            return;
        }

        $login = (string) ($_POST['login'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $member = $this->auth->attemptLogin($login, $password);
        if (!$member) {
            $this->view->render('login', [
                'pageTitle'   => 'Logowanie',
                'error'       => 'Nieprawidłowy login/e-mail lub hasło.',
                'currentUser' => null,
            ]);
            return;
        }

        View::redirect('/');
    }

    public function register(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (mb_strlen($username) < 3 || mb_strlen($username) > 64 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            $this->view->render('login', [
                'pageTitle'   => 'Rejestracja',
                'error'       => 'Podaj poprawny login (3-64 znaki), e-mail oraz hasło (min. 8 znaków).',
                'currentUser' => null,
            ]);
            return;
        }

        $result = $this->auth->register($username, $email, $password);
        if (!$result['ok']) {
            $this->view->render('login', [
                'pageTitle'   => 'Rejestracja',
                'error'       => $result['error'] ?? 'Błąd rejestracji.',
                'currentUser' => null,
            ]);
            return;
        }

        $this->auth->attemptLogin($username, $password);
        View::redirect('/');
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        $this->auth->logout();
        View::redirect('/');
    }
}
