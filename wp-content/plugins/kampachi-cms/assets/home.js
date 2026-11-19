(function () {
    'use strict';
    const slides = Array.from(document.querySelectorAll('.kch-slide'));
    if (slides.length < 2) return;
    let active = 0;
    window.setInterval(function () {
        slides[active].classList.remove('is-active');
        active = (active + 1) % slides.length;
        slides[active].classList.add('is-active');
    }, 9500);
})();
