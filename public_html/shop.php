<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/topbar.php';

require_login();

$shopOptions = [
    [
        'title' => 'Real Estate',
        'subtitle' => 'Secure your dream penthouse or a quiet townhome. Browse curated listings and reserve tours.',
        'image' => site_url('/assets/img/real.png'),
    ],
    [
        'title' => 'Vehicles',
        'subtitle' => 'Choose a chauffeur-ready sedan or a track-ready supercar. Stylized builds coming soon.',
        'image' => site_url('/assets/img/car.png'),
    ],
    [
        'title' => 'Food',
        'subtitle' => 'Fuel your persona with premium meals, supplements, and restorative rituals.',
        'image' => site_url('/assets/img/food.png'),
    ],
];

render_header('Shop');
render_topbar('shop');
?>

<main class="shop-page">

    <div class="shop-grid">
        <?php foreach ($shopOptions as $option): ?>
            <button
                type="button"
                class="shop-card"
                data-title="<?= escape($option['title']) ?>"
                data-subtitle="<?= escape($option['subtitle']) ?>"
                data-image="<?= escape($option['image']) ?>"
            >
                <span class="shop-card-image" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6), rgba(0,0,0,0.2)), url('<?= escape($option['image']) ?>');"></span>
                <h2 class="shop-card-title"><?= escape($option['title']) ?></h2>
            </button>
        <?php endforeach; ?>
    </div>
</main>

<div id="shop-overlay" class="shop-overlay" aria-hidden="true">
    <div class="shop-modal" role="dialog" aria-modal="true" aria-labelledby="shop-modal-title">
        <button type="button" class="shop-modal-close" aria-label="Close shop detail">×</button>
        <div class="shop-modal-hero"></div>
        <h2 id="shop-modal-title"></h2>
        <p class="shop-modal-description" id="shop-modal-description"></p>
        <div class="shop-modal-body">
            <p>Items will appear here soon. In the meantime, keep ascending—this area stays ready for new drops.</p>
            <div class="shop-modal-actions">
                <button type="button" class="shop-modal-action">Notify me</button>
                <button type="button" class="shop-modal-action shop-modal-action--ghost">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const overlay = document.getElementById('shop-overlay');
    const page = document.querySelector('.shop-page');
    const hero = overlay?.querySelector('.shop-modal-hero');
    const titleEl = document.getElementById('shop-modal-title');
    const descEl = document.getElementById('shop-modal-description');
    const closeButtons = overlay?.querySelectorAll('.shop-modal-close, .shop-modal-action--ghost');

    document.querySelectorAll('.shop-card').forEach(card => {
        card.addEventListener('click', () => {
            if (!overlay || !hero || !titleEl || !descEl) {
                return;
            }
            const title = card.dataset.title || 'Coming soon';
            const subtitle = card.dataset.subtitle || '';
            const image = card.dataset.image;

            titleEl.textContent = title;
            descEl.textContent = subtitle;
            hero.style.backgroundImage = image ? `url('${image}')` : '';

            overlay.classList.add('is-visible');
            page?.classList.add('is-blurred');
        });
    });

    const closeOverlay = () => {
        overlay.classList.remove('is-visible');
        page?.classList.remove('is-blurred');
    };

    closeButtons?.forEach(button => button.addEventListener('click', closeOverlay));
    overlay?.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeOverlay();
        }
    });
})();
</script>

<?php
render_footer();
?>

