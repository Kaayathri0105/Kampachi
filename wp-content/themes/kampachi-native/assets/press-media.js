(() => {
    const carousel = document.querySelector('[data-press-carousel]');
    if (!carousel) {
        return;
    }

    const track = carousel.querySelector('.kc-press-track');
    const controls = carousel.querySelector('[data-press-controls]');
    const previous = carousel.querySelector('[data-press-previous]');
    const next = carousel.querySelector('[data-press-next]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function updateControls() {
        const maxScroll = track.scrollWidth - track.clientWidth;
        controls.hidden = maxScroll <= 1;
        previous.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft >= maxScroll - 1;
    }

    function move(direction) {
        const width = track.firstElementChild.getBoundingClientRect().width;
        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        track.scrollBy({
            left: direction * (width + gap),
            behavior: reducedMotion.matches ? 'instant' : 'smooth',
        });
    }

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('scroll', updateControls, {passive: true});
    window.addEventListener('resize', updateControls);
    updateControls();
})();
