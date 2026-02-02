<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/game.php';

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => is_https(),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if ($scriptName === '') {
        return $base = '';
    }

    $dir = dirname($scriptName);
    $dir = str_replace('\\', '/', $dir);
    $dir = rtrim($dir, '/');

    if ($dir === '' || $dir === '.') {
        return $base = '';
    }

    return $base = $dir === '/' ? '' : $dir;
}

function site_url(string $path = ''): string
{
    $base = base_path();
    $trimmed = '/' . ltrim($path, '/');

    if ($trimmed === '/') {
        $trimmed = '';
    }

    if ($base === '') {
        return $trimmed === '' ? '/' : $trimmed;
    }

    return $base . $trimmed;
}

function render_nav(): void
{
    $user = $_SESSION['user'] ?? null;
    echo '<nav class="site-nav">';
    echo '<a class="nav-pill" href="' . site_url('') . '">Home</a>';
    if ($user) {
        if ($user['role'] === 'admin') {
            echo '<a class="nav-pill" href="' . site_url('/admin.php') . '">Admin</a>';
        }
        $profile = get_player_profile((int)$user['id']);
        $label = $profile['character_name'] ?? $user['username'];

        if (!empty($profile['gender']) && $profile['gender'] === 'female') {
            $svg = '<svg viewBox="0 0 32 32" role="presentation"><circle cx="16" cy="8" r="6"></circle><path d="M9 22c0-4.418 3.582-8 8-8s8 3.582 8 8v4h-5v4h-6v-4H9z"></path></svg>';
        } elseif (!empty($profile['gender']) && $profile['gender'] === 'male') {
            $svg = '<svg viewBox="0 0 32 32" role="presentation"><circle cx="16" cy="8" r="6"></circle><path d="M10 24c0-3.866 3.134-7 7-7s7 3.134 7 7H10zM16 16c-5.523 0-10 2.239-10 5v5h20v-5c0-2.761-4.477-5-10-5z"></path></svg>';
        } else {
            $initial = escape(strtoupper(substr($user['username'], 0, 1)));
            $svg = '<span>' . $initial . '</span>';
        }

        echo '<a class="profile-nav" href="' . site_url('/dashboard.php') . '">';
        echo '<span class="profile-avatar">' . $svg . '</span>';
        echo '<span class="profile-name">' . escape($label) . '</span>';
        echo '</a>';
    } else {
        echo '<a class="nav-pill" href="' . site_url('/login.php') . '">Login</a>';
        echo '<a class="nav-pill" href="' . site_url('/register.php') . '">Register</a>';
    }
    echo '</nav>';
}

function format_currency(float $amount): string
{
    return number_format($amount, 0, '.', ',');
}

function format_currency_compact(float $amount): string
{
    $absAmount = abs($amount);
    $sign = $amount < 0 ? '-' : '';

    $format = function (float $value, string $suffix) use ($sign): string {
        $rounded = round($value, 1);
        if ($rounded == floor($rounded)) {
            return $sign . number_format($rounded, 0) . $suffix;
        }
        return $sign . number_format($rounded, 1, '.', '') . $suffix;
    };

    if ($absAmount >= 1_000_000_000_000) {
        return $format($absAmount / 1_000_000_000_000, 'T');
    }
    if ($absAmount >= 1_000_000_000) {
        return $format($absAmount / 1_000_000_000, 'B');
    }
    if ($absAmount >= 1_000_000) {
        return $format($absAmount / 1_000_000, 'M');
    }
    if ($absAmount >= 1_000) {
        return $format($absAmount / 1_000, 'K');
    }

    return $sign . number_format($absAmount, 0);
}

function get_player_tier(int $user_id): string
{
    ensure_game_state($user_id);
    $money = get_user_money($user_id);

    if ($money >= 1000000) {
        return 'milestone_1m';
    }

    if ($money >= 1000) {
        return 'milestone_1k';
    }

    return 'beginner';
}

function get_avatar_image(int $user_id): string
{
    ensure_player_profile($user_id);
    $profile = get_player_profile($user_id);
    $genderRaw = strtolower(trim($profile['gender'] ?? ''));
    $genderKey = (str_contains($genderRaw, 'female') || $genderRaw === 'f') ? 'female' : 'male';
    $tier = get_player_tier($user_id);

    return site_url("/assets/img/{$tier}_{$genderKey}.png");
}

function render_header(string $title, bool $showNav = true): void
{
    start_secure_session();
    $safeTitle = escape($title);
    $appName = escape(APP_NAME);
    $cssUrl = site_url('/assets/style.css');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{$safeTitle} | {$appName}</title>
    <link rel="stylesheet" href="{$cssUrl}">
</head>
    <body>
<div id="sceneTransition" aria-hidden="true"></div>
<header class="site-header">
    <div class="content">
        <h1 class="brand">{$safeTitle}</h1>
        <p class="subtitle">Welcome to {$appName}</p>
        <div class="nav-wrap">
HTML;
    if ($showNav) {
        render_nav();
    }
    echo <<<HTML
        </div>
    </div>
</header>
<main class="main">
HTML;
}

function render_auth_page(string $title): void
{
    start_secure_session();
    $safeTitle = escape($title);
    $appName = escape(APP_NAME);
    $cssUrl = site_url('/assets/style.css');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{$safeTitle} | {$appName}</title>
    <link rel="stylesheet" href="{$cssUrl}">
</head>
    <body class="auth-background">
<div id="sceneTransition" aria-hidden="true"></div>
<main class="main auth-layout">
HTML;
}

function render_footer(): void
{
    $appName = escape(APP_NAME);
    $year = date('Y');
    $scriptUrl = site_url('/assets/transition.js');
    echo <<<HTML
</main>
<footer class="site-footer">
    <p>&copy; {$year} {$appName}. All rights reserved.</p>
</footer>
<script src="{$scriptUrl}"></script>
</body>
</html>
HTML;
}

function render_forbidden(string $message = '403 Forbidden'): void
{
    http_response_code(403);
    render_header('Forbidden');
    echo '<section class="card">';
    echo '<h2>Access Denied</h2>';
    echo '<p>' . escape($message) . '</p>';
    echo '<p><a href="' . site_url('') . '">Return home</a></p>';
    echo '</section>';
    render_footer();
}

