(function () {
    'use strict';

    document.querySelectorAll('[data-kc-home-hero]').forEach(function (hero) {
        const slides = Array.from(hero.querySelectorAll('[data-kc-hero-slide]'));
        if (!slides.length) return;

        const videos = slides.map(slide => slide.querySelector('video'));
        const controls = hero.querySelector('.kc-home-hero-controls');
        const sound = hero.querySelector('[data-kc-hero-sound]');
        const count = hero.querySelector('[data-kc-hero-count]');
        const previous = hero.querySelector('[data-kc-hero-prev]');
        const next = hero.querySelector('[data-kc-hero-next]');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let index = 0;
        let timer;
        let muted = true;

        const formatCount = (value) => String(value).padStart(2, '0');

        function updateControls() {
            if (count) count.textContent = formatCount(index + 1) + ' / ' + formatCount(slides.length);
            if (sound) {
                sound.hidden = !videos[index];
                sound.dataset.muted = String(muted);
                sound.setAttribute('aria-label', muted ? 'Turn sound on' : 'Mute sound');
            }
            if (controls) controls.hidden = slides.length === 1 && (!sound || sound.hidden);
        }

        function scheduleNext(delay = 7500) {
            window.clearTimeout(timer);
            if (slides.length > 1 && !motion.matches && !document.hidden) {
                timer = window.setTimeout(() => showSlide(index + 1), delay);
            }
        }

        function startActive() {
            window.clearTimeout(timer);
            const activeIndex = index;
            const video = videos[activeIndex];
            if (document.hidden || motion.matches) return;
            if (!video) {
                scheduleNext();
                return;
            }

            video.muted = muted;
            video.play().catch(() => {
                if (index !== activeIndex || document.hidden || motion.matches) return;
                if (!muted) {
                    muted = true;
                    video.muted = true;
                    updateControls();
                    video.play().catch(() => {
                        if (index === activeIndex) scheduleNext();
                    });
                } else {
                    scheduleNext();
                }
            });
        }

        function showSlide(target) {
            const previousVideo = videos[index];
            if (previousVideo) {
                previousVideo.pause();
                previousVideo.currentTime = 0;
            }
            index = (target + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                const active = slideIndex === index;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
            });
            updateControls();
            startActive();
        }

        if (previous) previous.addEventListener('click', () => showSlide(index - 1));
        if (next) next.addEventListener('click', () => showSlide(index + 1));
        if (sound) {
            sound.addEventListener('click', () => {
                const activeIndex = index;
                const video = videos[activeIndex];
                if (!video) return;
                muted = !muted;
                video.muted = muted;
                updateControls();
                if (video.paused) {
                    window.clearTimeout(timer);
                    video.play().catch(() => {
                        if (index === activeIndex) scheduleNext();
                    });
                }
            });
        }

        videos.forEach((video, slideIndex) => {
            if (!video) return;

            video.addEventListener('ended', () => {
                if (slideIndex === index && slides.length > 1 && !motion.matches && !document.hidden) {
                    showSlide(index + 1);
                }
            });
            video.addEventListener('error', () => {
                if (slideIndex === index) scheduleNext(1000);
            }, true);
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden && videos[index]) videos[index].pause();
            startActive();
        });
        motion.addEventListener('change', () => {
            if (motion.matches && videos[index]) videos[index].pause();
            startActive();
        });

        updateControls();
        startActive();
    });
})();
