<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/topbar.php';

require_login();

$user = current_user();
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT username, role, created_at, last_login FROM users WHERE id = :id');
$stmt->execute([':id' => $user['id']]);
$profileSummary = $stmt->fetch();

if (!has_player_profile($user['id'])) {
    header('Location: ' . site_url('/intro.php'));
    exit;
}

ensure_game_state($user['id']);
$money = get_user_money($user['id']);
$profile = get_player_profile($user['id']);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }

    $action = $_POST['action'] ?? '';

    if (empty($errors)) {
        if ($action === 'change_password') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($newPassword) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $update->execute([
                    ':hash' => $hash,
                    ':id' => $user['id'],
                ]);
                $success = 'Password updated successfully.';
            }
        } elseif ($action === 'delete_account') {
            $confirmation = trim($_POST['confirmation'] ?? '');

            if ($confirmation !== 'DELETE') {
                $errors[] = 'To delete your account type DELETE exactly.';
            } else {
                $pdo->beginTransaction();
                try {
                    $deleteProfile = $pdo->prepare('DELETE FROM player_profile WHERE user_id = :id');
                    $deleteProfile->execute([':id' => $user['id']]);

                    $deleteGame = $pdo->prepare('DELETE FROM game_state WHERE user_id = :id');
                    $deleteGame->execute([':id' => $user['id']]);

                    $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $deleteUser->execute([':id' => $user['id']]);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $_SESSION = [];

                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }

                session_destroy();
                header('Location: ' . site_url('/login.php'));
                exit;
            }
        }
    }
}

render_header('Dashboard');
render_topbar('dashboard');

?>
<?php if ($success): ?>
    <section class="card">
        <p class="status"><?= escape($success) ?></p>
    </section>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <section class="card">
        <ul class="message-list">
            <?php foreach ($errors as $error): ?>
                <li><?= escape($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card" data-anim data-delay="0">
    <h2>Profile Summary</h2>
    <p><strong>Username:</strong> <?= escape($profileSummary['username'] ?? $user['username']) ?></p>
    <p><strong>Role:</strong> <?= escape($profileSummary['role'] ?? $user['role']) ?></p>
    <p><strong>Account created:</strong> <?= escape($profileSummary['created_at'] ?? 'N/A') ?></p>
    <p><strong>Last login:</strong> <?= escape($profileSummary['last_login'] ?? 'Never') ?></p>
    <p><strong>Character:</strong> <?= escape($profile['character_name'] ?? '—') ?></p>
    <p><strong>Gender:</strong> <?= escape($profile['gender'] ?? '—') ?></p>
    <p><strong>Age:</strong> <?= escape((string)($profile['age'] ?? '—')) ?></p>
    <p><strong>Country:</strong> <?= escape($profile['country'] ?? '—') ?></p>
    <p><strong>Life Goal:</strong> <?= escape($profile['life_goal'] ?? '—') ?></p>
    <p><strong>Money:</strong> $<?= number_format($money) ?></p>
</section>

<section class="card" data-anim data-delay="0.2">
    <h2>Change Password</h2>
    <form method="post" action="<?= site_url('/dashboard.php') ?>" autocomplete="off">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="change_password">
        <label for="new_password">New Password</label>
        <input id="new_password" name="new_password" type="password" minlength="8" required class="input">

        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" required class="input">

        <button type="submit" class="btn btn-primary">Update password</button>
    </form>
</section>

<section class="card" data-anim data-delay="0.4">
    <h2>Session</h2>
    <p><a href="<?= site_url('/logout.php') ?>" class="btn btn-ghost">Logout</a></p>
</section>

<section class="card danger-zone" data-anim data-delay="0.6">
    <h2>Danger Zone</h2>
    <p>Deleting your account is permanent. All associated data will be removed.</p>
    <form method="post" action="<?= site_url('/dashboard.php') ?>" autocomplete="off">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete_account">
        <label for="confirmation">Type DELETE to confirm</label>
        <input id="confirmation" name="confirmation" type="text" required class="input">
        <button type="submit" class="btn btn-danger">Delete account</button>
    </form>
</section>

<?php
render_footer();

