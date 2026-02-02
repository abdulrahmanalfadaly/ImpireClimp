<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/topbar.php';

require_login();

$user = current_user();

if (!has_player_profile($user['id'])) {
    header('Location: ' . site_url('/intro.php'));
    exit;
}

ensure_game_state($user['id']);
ensure_player_stats($user['id']);
    $stats = get_player_stats($user['id']);
$profile = get_player_profile($user['id']);
$avatarImage = get_avatar_image($user['id']);
$avatarTier = get_player_tier($user['id']);
$netWorth = get_user_money($user['id']);
$balance = get_game_balance($user['id']);
$netWorth = get_user_money($user['id']);

render_header('Player Home');
render_topbar('home');
?>
<div class="home-center-row">
    <section class="home-status-panel">
        <section class="home-status-card <?= escape($avatarTier) ?>" aria-live="polite" data-anim data-delay="0.1" data-tier="<?= escape($avatarTier) ?>" data-networth="<?= escape((string)$netWorth) ?>">
            <div class="home-status-grid<?= $netWorth >= 1000 ? ' with-balance' : '' ?>">
                <div class="home-status-avatar" data-anim data-delay="0.3">
                    <div class="home-avatar-card">
                        <div class="home-avatar-portrait">
                            <img src="<?= escape($avatarImage) ?>" alt="Character avatar" class="home-avatar-img">
                        </div>
                        <div class="home-avatar-details">
                            <p class="home-avatar-name"><?= escape($profile['character_name'] ?? $user['username']) ?></p>
                            <div class="home-life-goal-block">
                                <span class="home-life-goal-label">Life Goal</span>
                                <p class="home-life-goal-text">
                                    <?= escape($profile['life_goal'] ?? 'Forge your path through Empire Climb') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="home-status-bars" data-anim data-delay="0.4">
                    <?php
                    $statDefinitions = [
                        ['key' => 'health', 'label' => 'Health', 'class' => 'health', 'icon' => '❤️'],
                        ['key' => 'energy', 'label' => 'Energy', 'class' => 'energy', 'icon' => '⚡'],
                        ['key' => 'happiness', 'label' => 'Happiness', 'class' => 'happiness', 'icon' => '😊'],
                    ];

                    foreach ($statDefinitions as $index => $definition):
                        $value = (int)($stats[$definition['key']] ?? 0);
                        if ($value < 0) {
                            $value = 0;
                        } elseif ($value > 100) {
                            $value = 100;
                        }
                        $delay = 0.4 + ($index * 0.08);
                    ?>
                        <div class="stat-row">
                            <div class="stat-row-main">
                                <span class="stat-icon <?= escape($definition['class']) ?>" aria-hidden="true">
                                    <?= escape($definition['icon']) ?>
                                </span>
                                <div class="stat-bar-wrapper">
                                    <div class="stat-bar-label"><?= escape($definition['label']) ?></div>
                                    <div class="stat-meter" data-stat="<?= escape((string)$value) ?>">
                                        <span class="stat-meter-fill <?= escape($definition['class']) ?>"
                                              style="--target-width: <?= $value ?>%; --bar-delay: <?= number_format($delay, 2) ?>s;"></span>
                                    </div>
                                </div>
                            </div>
                            <span class="stat-percent"><?= escape((string)$value) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($netWorth >= 1000): ?>
                    <?php $balanceTierClass = $netWorth >= 1000000 ? 'tier-1m' : 'tier-1k'; ?>
                    <div class="home-status-balance" data-anim data-delay="0.55">
                        <div
                            class="balance-card <?= escape($balanceTierClass) ?>"
                            style="background-image:url('<?= site_url('/assets/img/' . ($netWorth >= 1000000 ? '1m_card.png' : '1k_card.png')) ?>');"
                        >
                            <div class="balance-card-amount" data-full="$<?= escape(format_currency($balance)) ?>">
                                <?= escape(format_currency_compact($balance)) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tier-overlay" aria-hidden="true"></div>
            <div class="tier-event-popup" role="status" aria-live="polite"></div>
        </section>
    </section>

</div>
<?php
render_footer();
?>
<script>
(() => {
    const card = document.querySelector('.home-status-card');
    if (!card) {
        return;
    }

    const tierOrder = { beginner: 0, milestone_1k: 1, milestone_1m: 2 };
    const currentTier = card.dataset.tier;
    const prevTier = sessionStorage.getItem('empireTier');
    const overlay = card.querySelector('.tier-overlay');
    const popup = card.querySelector('.tier-event-popup');
    const netWorth = Number(card.dataset.networth) || 0;

    const currentIndex = tierOrder[currentTier] ?? 0;
    const prevIndex = prevTier ? (tierOrder[prevTier] ?? 0) : null;

    if (overlay && popup && prevTier && currentIndex > (prevIndex ?? -1)) {
        const amount = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(netWorth);
        popup.textContent = `You reached ${amount}`;
        card.classList.add('tier-transition');
        overlay.classList.add('is-active');
        popup.classList.add('is-visible');

        setTimeout(() => {
            popup.classList.remove('is-visible');
        }, 1600);

        setTimeout(() => {
            overlay.classList.add('fade-out');
        }, 1400);

        setTimeout(() => {
            overlay.classList.remove('is-active', 'fade-out');
            card.classList.add('tier-flash');
            setTimeout(() => card.classList.remove('tier-flash'), 600);
        }, 3200);
    }

    sessionStorage.setItem('empireTier', currentTier);
})();
</script>

