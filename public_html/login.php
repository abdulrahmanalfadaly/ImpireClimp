
<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$identifierValue = $_POST['identifier'] ?? '';

$lockedUntil = $_SESSION['login_locked_until'] ?? 0;
$now = time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }

    if ($lockedUntil > $now) {
        $errors[] = 'Too many failed attempts. Please wait a few minutes before trying again.';
    } else {
        $username = trim($_POST['identifier'] ?? '');
        $password = $_POST['security_key'] ?? '';
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;

            if ($_SESSION['login_failures'] >= 5) {
                $_SESSION['login_locked_until'] = $now + 300;
                $errors[] = 'Too many failed attempts. Please wait 5 minutes before retrying.';
            } else {
                $errors[] = 'Invalid identifier or security key.';
            }
        } else {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
            session_regenerate_id(true);

            $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            $update->execute([':id' => $user['id']]);

            if (!has_player_profile($user['id'])) {
                header('Location: ' . site_url('/intro.php'));
            } else {
                header('Location: ' . site_url('/home.php'));
            }
            exit;
        }
    }
}

render_auth_page('Login');
?>
<div class="auth-bg">
    <div class="auth-card" data-anim data-delay="0">
        <div class="auth-title-row">
            <img src="<?= site_url('/assets/img/logo.png') ?>" alt="Empire Climb logo" class="auth-logo">
            <h1>EMPIRE CLIMB</h1>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="message-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= site_url('/login.php') ?>" autocomplete="off" data-anim data-delay="0.2">
            <?= csrf_input() ?>
            <label class="auth-label" for="identifier">IDENTIFIER</label>
            <input id="identifier" name="identifier" type="text" class="auth-input" value="<?= escape($identifierValue) ?>">

            <label class="auth-label" for="security_key">SECURITY KEY</label>
            <input id="security_key" name="security_key" type="password" class="auth-input">

            <button type="submit" class="auth-button">ACCESS</button>
        </form>
        <div class="auth-links">
            <a href="<?= site_url('/register.php') ?>">Initiate new persona.</a>
        </div>
    </div>
</div>
<?php
render_footer();
 