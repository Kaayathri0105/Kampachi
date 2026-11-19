(() => {
    'use strict';

    document.querySelectorAll('.outlets-grid--carousel').forEach((track) => {
        const section = track.closest('.outlets-section');
        const controls = section?.querySelector('.outlets-carousel-controls');
        const previous = controls?.querySelector('.outlets-carousel-prev');
        const next = controls?.querySelector('.outlets-carousel-next');

        if (!previous || !next || track.children.length <= 3) {
            return;
        }

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let destination = Math.abs(track.scrollLeft);
        const syncPosition = () => {
            destination = Math.abs(track.scrollLeft);
        };
        const updateControls = () => {
            const maximum = track.scrollWidth - track.clientWidth;
            previous.disabled = maximum <= 2;
            next.disabled = maximum <= 2;
            controls.hidden = maximum <= 2;
        };

        const move = (direction) => {
            const firstCard = track.firstElementChild;
            const gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
            const step = firstCard.getBoundingClientRect().width + gap;
            const rtl = window.getComputedStyle(track).direction === 'rtl';
            const maximum = Math.max(0, track.scrollWidth - track.clientWidth);

            if (maximum <= 2 || step <= 0) {
                return;
            }

            if (direction > 0 && destination >= maximum - 2) {
                destination = 0;
            } else if (direction < 0 && destination <= 2) {
                destination = maximum;
            } else {
                destination = Math.min(maximum, Math.max(0, destination + direction * step));
            }

            track.scrollTo({
                left: destination * (rtl ? -1 : 1),
                behavior: reducedMotion.matches ? 'auto' : 'smooth',
            });
        };

        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('scroll', updateControls, { passive: true });
        track.addEventListener('scrollend', syncPosition, { passive: true });
        track.addEventListener('pointerdown', syncPosition, { passive: true });
        track.addEventListener('touchstart', syncPosition, { passive: true });
        track.addEventListener('wheel', syncPosition, { passive: true });
        track.addEventListener('keydown', (event) => {
            if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            const rtl = window.getComputedStyle(track).direction === 'rtl';
            move((event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1));
        });

        if ('ResizeObserver' in window) {
            new ResizeObserver(updateControls).observe(track);
        } else {
            window.addEventListener('resize', updateControls);
        }
        updateControls();
    });
})();
