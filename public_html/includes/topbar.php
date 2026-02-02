<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/db.php';

function render_topbar(string $activeTab = 'home'): void
{
    $user = current_user();
    $money = $user ? get_user_money($user['id']) : 0;
    $day = 1;
    $createdAt = null;
    $stats = null;
    $statusLevel = null;

    if ($user) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $createdAt = $stmt->fetchColumn() ?? null;
        ensure_player_stats($user['id']);
        $stats = get_player_stats($user['id']);
    }

    if ($createdAt !== null) {
        try {
            $createdDate = new DateTime($createdAt);
            $nowDate = new DateTime();
            $interval = $createdDate->diff($nowDate);
            $day = (int) $interval->days + 1;
            if ($day < 1) {
                $day = 1;
            }
        } catch (Exception $e) {
            $day = 1;
        }
    }

    $overallWidth = 0;
    if ($stats !== null) {
        $health = $stats['health'];
        $energy = $stats['energy'];
        $happiness = $stats['happiness'];
        $average = ($health + $energy + $happiness) / 3;
        $overallWidth = max(0, min(100, $average));

        if ($average >= 75) {
            $statusLevel = ['class' => 'thriving', 'title' => 'Thriving'];
        } elseif ($average >= 50) {
            $statusLevel = ['class' => 'stable', 'title' => 'Stable'];
        } elseif ($average >= 25) {
            $statusLevel = ['class' => 'strained', 'title' => 'Strained'];
        } else {
            $statusLevel = ['class' => 'critical', 'title' => 'Critical'];
        }

        if ($health < 20) {
            $overallWidth = max(0, $health);
            $statusLevel = ['class' => 'critical', 'title' => 'Critical'];
        }
    }

    $logoPath = file_exists(__DIR__ . '/../assets/img/logo.png') ? site_url('/assets/img/logo.png') : null;

    echo '<nav class="topbar" data-anim data-delay="0.1">';
    echo '<div class="topbar-left">';
    if ($logoPath) {
        echo '<img class="topbar-logo" src="' . $logoPath . '" alt="Empire Climb logo">';
    } else {
        echo '<span class="topbar-mark"></span>';
    }
    echo '<span class="topbar-title">Empire Climb</span>';
    echo '</div>';

    echo '<div class="topbar-center">';
    $homeClass = $activeTab === 'home' ? 'topbar-tab is-active' : 'topbar-tab';
    echo '<a class="' . $homeClass . '" href="' . site_url('/home.php') . '">Home</a>';
    $shopClass = $activeTab === 'shop' ? 'topbar-tab is-active' : 'topbar-tab';
    echo '<a class="' . $shopClass . '" href="' . site_url('/shop.php') . '">Shop</a>';
    if ($user && $user['role'] === 'admin') {
        $adminClass = $activeTab === 'admin' ? 'topbar-tab is-active' : 'topbar-tab';
        echo '<a class="' . $adminClass . '" href="' . site_url('/admin.php') . '">Admin</a>';
    }
    echo '</div>';

    echo '<div class="topbar-right">';
    echo '<span class="topbar-status topbar-day">Day ' . $day . '</span>';
    echo '<span class="topbar-divider" aria-hidden="true">|</span>';
    echo '<div class="topbar-stat-block">';
    echo '<span class="topbar-stat-label">Net worth</span>';
    echo '<span class="topbar-stat-value">$' . number_format($money) . '</span>';
    echo '</div>';
    echo '<span class="topbar-divider" aria-hidden="true">|</span>';

    if ($statusLevel !== null) {
        echo '<div class="topbar-overall-status ' . escape($statusLevel['class']) . '" title="' . escape($statusLevel['title']) . '">';
        echo '<span class="topbar-overall-status-track"></span>';
        echo '<span class="topbar-overall-status-fill" style="width: ' . number_format($overallWidth, 2) . '%;"></span>';
        echo '</div>';
    }

    if ($user) {
        $profile = get_player_profile($user['id']);
        $label = $user['username'];
        $gender = $profile['gender'] ?? '';
        $svg = '';

        if ($gender === 'female') {
            $svg = '<svg viewBox="0 0 32 32" role="presentation"><circle cx="16" cy="8" r="6"></circle><path d="M9 22c0-4.418 3.582-8 8-8s8 3.582 8 8v4h-5v4h-6v-4H9z"></path></svg>';
        } elseif ($gender === 'male') {
            $svg = '<svg viewBox="0 0 32 32" role="presentation"><circle cx="16" cy="8" r="6"></circle><path d="M10 24c0-3.866 3.134-7 7-7s7 3.134 7 7H10zM16 16c-5.523 0-10 2.239-10 5v5h20v-5c0-2.761-4.477-5-10-5z"></path></svg>';
        } else {
            $initial = escape(strtoupper(substr($user['username'], 0, 1)));
            $svg = '<span>' . $initial . '</span>';
        }

        echo '<a class="topbar-profile" href="' . site_url('/dashboard.php') . '">';
        echo '<span class="topbar-profile-avatar">' . $svg . '</span>';
        echo '<span class="topbar-profile-name">' . escape($label) . '</span>';
        echo '</a>';
    }

    echo '</div>';
    echo '</nav>';
    echo '<script>document.body.classList.add("with-topbar");</script>';
}

