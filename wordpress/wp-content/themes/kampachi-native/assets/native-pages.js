(() => {
    'use strict';
    const slideshows = document.querySelectorAll('.kc-landing-hero:not([data-kc-home-hero])');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    slideshows.forEach((hero) => {
        const slides = Array.from(hero.querySelectorAll('.kc-landing-slide'));
        const video = hero.querySelector('video');
        if (reduceMotion && video) {
            video.pause();
        }
        if (reduceMotion || slides.length < 2) {
            return;
        }
        let current = 0;
        window.setInterval(() => {
            if (document.hidden) {
                return;
            }
            slides[current].classList.remove('is-active');
            current = (current + 1) % slides.length;
            slides[current].classList.add('is-active');
        }, 9000);
    });
})();
