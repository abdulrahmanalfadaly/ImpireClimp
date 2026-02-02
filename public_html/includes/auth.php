<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function clear_user_session(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function current_user(): ?array
{
    start_secure_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    $user = current_user();

    if ($user === null) {
        header('Location: ' . site_url('/login.php'));
        exit;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);

    if ($stmt->fetch() === false) {
        clear_user_session();
        header('Location: ' . site_url('/login.php'));
        exit;
    }
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_admin(): void
{
    if (!is_admin()) {
        render_forbidden('You need admin rights to access this page.');
        exit;
    }
}
