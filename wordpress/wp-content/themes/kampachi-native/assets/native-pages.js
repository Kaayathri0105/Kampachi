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

(() => {
    'use strict';

    const canMagnify = window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
    document.querySelectorAll('.kc-sake-bottle-card-image > .kc-sake-bottle-photo').forEach(photo => {
        const frame = photo.parentElement;
        let magnifiedPhoto;

        frame.addEventListener('pointermove', event => {
            if (!canMagnify.matches || event.pointerType !== 'mouse' || !photo.complete || !photo.naturalWidth) {
                return;
            }

            const bounds = frame.getBoundingClientRect();
            if (!bounds.width || !bounds.height) {
                return;
            }

            if (!magnifiedPhoto) {
                const lens = document.createElement('span');
                lens.className = 'kc-sake-magnifier';
                lens.setAttribute('aria-hidden', 'true');
                magnifiedPhoto = document.createElement('img');
                magnifiedPhoto.className = 'kc-sake-bottle-photo';
                magnifiedPhoto.alt = '';
                lens.append(magnifiedPhoto);
                frame.append(lens);
            }

            const source = photo.currentSrc || photo.src;
            if (magnifiedPhoto.src !== source) {
                magnifiedPhoto.src = source;
            }

            frame.style.setProperty('--kc-sake-lens-x', `${100 * (event.clientX - bounds.left) / bounds.width}%`);
            frame.style.setProperty('--kc-sake-lens-y', `${100 * (event.clientY - bounds.top) / bounds.height}%`);
            frame.setAttribute('data-kc-sake-magnifying', '');
        });

        const hideLens = () => frame.removeAttribute('data-kc-sake-magnifying');
        frame.addEventListener('pointerleave', hideLens);
        frame.addEventListener('pointercancel', hideLens);
        canMagnify.addEventListener('change', hideLens);
    });
})();
