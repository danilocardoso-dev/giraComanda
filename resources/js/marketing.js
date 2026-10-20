export const setupMarketingPage = () => {
    const page = document.querySelector('.marketing-page');

    if (! page) {
        return;
    }

    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const motionToggle = page.querySelector('[data-motion-toggle]');
    const revealElements = [...page.querySelectorAll('[data-reveal]')];
    const metricElements = [...page.querySelectorAll('.landing-metric')];
    let motionPaused = false;
    let revealObserver = null;

    const reveal = (element) => {
        element.classList.remove('is-pending');
        element.classList.add('is-revealed');
        revealObserver?.unobserve(element);
    };

    const updateMotion = () => {
        const enabled = ! motionPreference.matches && ! motionPaused;
        page.dataset.motion = enabled ? 'enabled' : 'reduced';
        revealObserver?.disconnect();

        if (motionToggle) {
            motionToggle.hidden = motionPreference.matches;
            motionToggle.textContent = motionPaused ? 'Ativar animações' : 'Pausar animações';
            motionToggle.setAttribute('aria-pressed', String(motionPaused));
        }

        if (! enabled) {
            metricElements.forEach((element) => element.getAnimations?.().forEach((animation) => animation.cancel()));
        }

        if (! enabled || ! ('IntersectionObserver' in window)) {
            revealElements.forEach(reveal);

            return;
        }

        revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    reveal(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

        revealElements.forEach((element) => {
            if (element.classList.contains('is-revealed') || element.getBoundingClientRect().top < window.innerHeight - 24) {
                reveal(element);
            } else {
                element.classList.add('is-pending');
                revealObserver.observe(element);
            }
        });
    };

    motionToggle?.addEventListener('click', () => {
        motionPaused = ! motionPaused;
        updateMotion();
    });

    motionPreference.addEventListener('change', updateMotion);

    page.addEventListener('focusin', (event) => {
        const pendingElement = event.target.closest('[data-reveal].is-pending');

        if (pendingElement) {
            reveal(pendingElement);
        }
    });

    page.querySelectorAll('#average-ticket, #missed-tabs').forEach((input) => {
        input.addEventListener('input', () => {
            if (page.dataset.motion !== 'enabled') {
                return;
            }

            metricElements.forEach((element) => {
                element.getAnimations?.().forEach((animation) => animation.cancel());
                element.animate?.([
                    { opacity: 0.55, transform: 'translateY(4px)' },
                    { opacity: 1, transform: 'translateY(0)' },
                ], { duration: 260, easing: 'ease-out' });
            });
        });
    });

    const hero = page.querySelector('.landing-hero');

    if (hero && 'IntersectionObserver' in window) {
        const heroObserver = new IntersectionObserver(([entry]) => {
            hero.dataset.inView = String(entry.isIntersecting);
        });

        heroObserver.observe(hero);
    }

    updateMotion();
};
