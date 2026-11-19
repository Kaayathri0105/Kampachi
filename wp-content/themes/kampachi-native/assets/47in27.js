(function () {
    'use strict';

    const slides = Array.from(document.querySelectorAll('.kc47-hero-slide'));
    if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        let active = 0;
        window.setInterval(function () {
            slides[active].classList.remove('is-active');
            active = (active + 1) % slides.length;
            slides[active].classList.add('is-active');
        }, 9500);
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const video = document.querySelector('.kc47-hero-video');
        if (video) video.pause();
    }

})();
