const playChime = () => {
    const AudioContextClass = window.AudioContext ?? window.webkitAudioContext;

    if (! AudioContextClass) {
        return;
    }

    try {
        const context = new AudioContextClass();

        [0, 0.18].forEach((offset, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const startsAt = context.currentTime + offset;

            oscillator.frequency.value = index === 0 ? 880 : 1175;
            gain.gain.setValueAtTime(0.0001, startsAt);
            gain.gain.exponentialRampToValueAtTime(0.25, startsAt + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, startsAt + 0.3);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(startsAt);
            oscillator.stop(startsAt + 0.32);
        });
    } catch (_error) {
        // Browsers may block audio until the user interacts with the page.
    }
};

export const setupMenuOrderNotifications = () => {
    const source = document.querySelector('[data-menu-orders-pending-url]');

    if (! source) {
        return;
    }

    const url = source.dataset.menuOrdersPendingUrl;
    const badges = document.querySelectorAll('[data-menu-orders-badge]');
    const ordersPage = document.querySelector('[data-menu-orders-page]');
    const storageKey = 'giracomanda.menu-orders.latest';
    let lastSeenId = null;

    try {
        lastSeenId = Number(window.localStorage.getItem(storageKey) ?? 0);
    } catch (_error) {
        lastSeenId = 0;
    }

    const refresh = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (! response.ok) {
                return;
            }

            const { count, latest_id: latestId } = await response.json();

            badges.forEach((badge) => {
                badge.textContent = String(count);
                badge.hidden = count === 0;
            });

            if (latestId > lastSeenId) {
                if (lastSeenId > 0 || count > 0) {
                    playChime();
                }

                lastSeenId = latestId;

                try {
                    window.localStorage.setItem(storageKey, String(latestId));
                } catch (_error) {
                    // The chime may repeat on another page when storage is unavailable.
                }
            }

            const isTyping = document.activeElement?.matches('input, textarea');

            if (ordersPage && Number(ordersPage.dataset.latestId ?? 0) < latestId && ! isTyping) {
                window.location.reload();
            }
        } catch (_error) {
            // A temporary network failure only delays the next refresh.
        }
    };

    refresh();
    window.setInterval(refresh, 10000);
};
