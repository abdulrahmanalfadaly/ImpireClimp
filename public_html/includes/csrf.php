<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    $token = escape(csrf_token());
    return <<<HTML
<input type="hidden" name="csrf_token" value="{$token}">
HTML;
}

function verify_csrf(): void
{
    start_secure_session();

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($sessionToken, $postedToken)) {
        throw new RuntimeException('Invalid CSRF token');
    }
}

