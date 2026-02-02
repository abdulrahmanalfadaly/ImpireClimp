<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$usernameValue = $_POST['username'] ?? '';
$registered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters and contain only letters, numbers, or underscores.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo = get_pdo();
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => $hash,
            ]);
            $userId = (int)$pdo->lastInsertId();
            ensure_game_state($userId);
            $_SESSION['user'] = [
                'id' => $userId,
                'username' => $username,
                'role' => 'user',
            ];
            $_SESSION['registration_success'] = true;
            session_regenerate_id(true);
            $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $registered = true;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] ?? null === 1062) {
                $errors[] = 'That username is already taken.';
            } else {
                throw $e;
            }
        }
    }
}

render_auth_page('Register');
?>
<div class="auth-bg">
    <div class="auth-card" data-anim data-delay="0">
        <div class="auth-title-row">
            <img src="<?= site_url('/assets/img/logo.png') ?>" alt="Empire Climb logo" class="auth-logo">
            <h1>EMPIRE CLIMB</h1>
        </div>
        <?php if ($registered): ?>
            <div class="alert alert-success alert-success-inline">
                <p>Account created Successfully!</p>
                <p class="starting-line">Starting account... <span class="intro-spinner" aria-hidden="true"></span></p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="message-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= site_url('/register.php') ?>" autocomplete="off" data-anim data-delay="0.2">
                <?= csrf_input() ?>
                <label class="auth-label" for="username">IDENTIFIER</label>
                <input id="username" name="username" type="text" class="auth-input" maxlength="30" value="<?= escape($usernameValue) ?>">

                <label class="auth-label" for="password">SECURITY KEY</label>
                <input id="password" name="password" type="password" class="auth-input">

                <label class="auth-label" for="confirm_password">CONFIRM SECURITY KEY</label>
                <input id="confirm_password" name="confirm_password" type="password" class="auth-input">

                <button type="submit" class="auth-button">ACCESS</button>
            </form>
            <div class="auth-links">
                <a href="<?= site_url('/login.php') ?>">Return to login.</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($registered): ?>
    <script>
        setTimeout(() => {
            window.location.href = '<?= site_url('/intro.php') ?>';
        }, 3200);
    </script>
<?php endif; ?>
<?php
render_footer();

