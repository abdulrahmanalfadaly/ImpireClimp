(() => {
    const body = document.body;
    const overlay = document.getElementById('sceneTransition');
    const animTargets = document.querySelectorAll('[data-anim]');
    let transitionActive = false;

    const animateEntrance = () => {
        animTargets.forEach((node) => {
            const delaySec = parseFloat(node.dataset.delay) || 0;
            window.setTimeout(() => node.classList.add('in'), delaySec * 1000);
        });
    };

    const startTransition = () => {
        if (transitionActive || !overlay) {
            return Promise.resolve();
        }
        transitionActive = true;
        overlay.classList.add('is-active');
        body.classList.add('is-transitioning');
        return new Promise((resolve) => window.setTimeout(resolve, 150));
    };

    const endTransition = () => {
        if (!overlay) return;
        transitionActive = false;
        body.classList.remove('is-transitioning');
        overlay.classList.remove('is-active');
    };

    const isInternalLink = (link) => {
        if (!link || link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('href')?.startsWith('mailto:')) {
            return false;
        }
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) {
            return false;
        }
        const url = new URL(href, window.location.href);
        return url.origin === window.location.origin;
    };

    const handleLinkClick = (event) => {
        const anchor = event.target.closest('a');
        if (!anchor || !isInternalLink(anchor)) {
            return;
        }
        event.preventDefault();
        startTransition().then(() => {
            window.location.href = anchor.href;
        });
    };

    const handleFormSubmit = (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const submit = form.querySelector('[type="submit"], button:not([type])');
        if (!submit || submit.disabled) {
            return;
        }
        submit.dataset.originalText = submit.textContent?.trim() ?? '';
        submit.disabled = true;
        submit.textContent = 'Entering';
        const dot = document.createElement('span');
        dot.className = 'dot-loader';
        submit.appendChild(dot);
        startTransition();
    };

    const handlePageShow = (event) => {
        if (event.persisted) {
            endTransition();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        animateEntrance();
        window.setTimeout(() => endTransition(), 300);
        document.addEventListener('click', handleLinkClick);
        document.addEventListener('submit', handleFormSubmit, true);
    });

    window.addEventListener('pageshow', handlePageShow);
})();

