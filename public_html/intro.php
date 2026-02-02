<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$user = current_user();

if (has_player_profile($user['id'])) {
    header('Location: ' . site_url('/home.php'));
    exit;
}

$errors = [];
$stepErrors = [];
$lastStep = (int)($_POST['last_step'] ?? 1);

$characterNameValue = $_POST['character_name'] ?? '';
$genderValue = $_POST['gender'] ?? '';
$ageValue = $_POST['age'] ?? '';
$countryValue = $_POST['country'] ?? '';
$lifeGoalValue = $_POST['life_goal'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }

    $characterName = trim($_POST['character_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $age = $_POST['age'] ?? '';
    $country = trim($_POST['country'] ?? '');
    $lifeGoal = $_POST['life_goal'] ?? '';

    if (empty($errors)) {
        $ageValue = filter_var($age, FILTER_VALIDATE_INT);

        if ($characterName === '' || strlen($characterName) < 2 || strlen($characterName) > 30) {
            $errors[] = 'Character name must be between 2 and 30 characters.';
            $stepErrors[1] = $errors[count($errors) - 1];
            $lastStep = 1;
        } elseif (!in_array($gender, ['male', 'female'], true)) {
            $errors[] = 'Please select a gender.';
            $stepErrors[2] = $errors[count($errors) - 1];
            $lastStep = 2;
        } elseif ($ageValue === false || $ageValue < 1) {
            $errors[] = 'Enter a valid age.';
            $stepErrors[3] = $errors[count($errors) - 1];
            $lastStep = 3;
        } elseif ($country === '') {
            $errors[] = 'Country is required.';
            $stepErrors[4] = $errors[count($errors) - 1];
            $lastStep = 4;
        } elseif ($lifeGoal === '') {
            $errors[] = 'A life goal is required.';
            $stepErrors[5] = $errors[count($errors) - 1];
            $lastStep = 5;
        }
    }

    if (empty($errors)) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO player_profile (user_id, character_name, gender, age, country, life_goal) VALUES (:user_id, :character_name, :gender, :age, :country, :life_goal)');
        $stmt->execute([
            ':user_id' => $user['id'],
            ':character_name' => $characterName,
            ':gender' => $gender,
            ':age' => $ageValue,
            ':country' => $country,
            ':life_goal' => $lifeGoal,
        ]);

        header('Location: ' . site_url('/home.php'));
        exit;
    }
}

render_auth_page('Intro');
?>
<div class="auth-bg">
    <section id="intro-stage" class="intro-stage" aria-live="polite" data-anim data-delay="0">
        <div id="intro-message" class="intro-message"></div>
        <button type="button" id="skip-intro" class="skip-intro">Skip Intro</button>
    </section>

    <audio id="intro-audio" src="<?= site_url('/assets/intro.mp3') ?>" loop preload="auto"></audio>

    <section id="character-creation" class="card intro-card intro-stepper" aria-hidden="true" data-anim data-delay="0.8">
        <div class="auth-title-row">
            <img src="<?= site_url('/assets/img/logo.png') ?>" alt="Empire Climb logo" class="auth-logo">
            <h1>EMPIRE CLIMB</h1>
        </div>
        <form method="post" action="<?= site_url('/intro.php') ?>" class="stepper" autocomplete="off">
        <?= csrf_input() ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="message-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <input type="hidden" id="character_name_hidden" name="character_name" value="<?= escape($characterNameValue) ?>">
        <input type="hidden" id="gender_hidden" name="gender" value="<?= escape($genderValue) ?>">
        <input type="hidden" id="age_hidden" name="age" value="<?= escape($ageValue) ?>">
        <input type="hidden" id="country_hidden" name="country" value="<?= escape($countryValue) ?>">
        <input type="hidden" id="life_goal_hidden" name="life_goal" value="<?= escape($lifeGoalValue) ?>">
        <input type="hidden" id="last_step" name="last_step" value="<?= $lastStep ?>">

        <div class="step is-active" data-step="1">
            <label for="character_name_input">Character Name</label>
            <input id="character_name_input" class="input" type="text" data-target="character_name_hidden" required value="<?= escape($characterNameValue) ?>">
            <p class="step-error" role="alert"><?= escape($stepErrors[1] ?? '') ?></p>
        </div>

        <div class="step" data-step="2">
            <p>Select Gender</p>
            <div class="card-grid">
                <button type="button" class="gender-card" data-value="male">Male</button>
                <button type="button" class="gender-card" data-value="female">Female</button>
            </div>
            <p class="step-error" role="alert"><?= escape($stepErrors[2] ?? '') ?></p>
        </div>

        <div class="step" data-step="3">
            <label for="age_input">Age</label>
            <input id="age_input" class="input" type="number" min="1" data-target="age_hidden" required value="<?= escape($ageValue) ?>">
            <p class="step-error" role="alert"><?= escape($stepErrors[3] ?? '') ?></p>
        </div>

        <div class="step" data-step="4">
            <label for="country_input">Country</label>
            <input id="country_input" class="input" type="text" data-target="country_hidden" required value="<?= escape($countryValue) ?>">
            <p class="step-error" role="alert"><?= escape($stepErrors[4] ?? '') ?></p>
        </div>

        <div class="step" data-step="5">
            <p>Life Goal</p>
            <input id="life_goal_input" class="input" type="text" data-target="life_goal_hidden" placeholder="e.g., build a stable life and become financially free" required value="<?= escape($lifeGoalValue) ?>">
            <p class="step-error" role="alert"><?= escape($stepErrors[5] ?? '') ?></p>
        </div>

        <div class="stepper-controls">
            <button type="button" class="btn btn-ghost step-back">Back</button>
            <button type="button" class="btn btn-primary step-next">Next</button>
        </div>
    </form>
    </section>
</div>
<script src="<?= site_url('/assets/intro.js') ?>"></script>
<?php
render_footer();

