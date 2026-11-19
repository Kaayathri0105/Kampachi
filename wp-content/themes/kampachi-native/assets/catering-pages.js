(function () {
    'use strict';

    document.querySelectorAll('[data-kc-hero]').forEach(function (hero) {
        const slides = Array.from(hero.querySelectorAll('[data-kc-hero-slide]'));
        const count = hero.querySelector('[data-kc-hero-count]');
        const toggle = hero.querySelector('[data-kc-hero-toggle]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let active = 0;
        let playing = !reducedMotion.matches;
        let timer = null;
        let touchStartX = null;

        if (slides.length === 0) {
            return;
        }

        function cancelTimer() {
            if (timer !== null) {
                window.clearTimeout(timer);
                timer = null;
            }
        }

        function updateToggle() {
            if (!toggle) {
                return;
            }

            toggle.textContent = playing ? 'Pause' : 'Play';
            toggle.setAttribute('aria-label', playing ? 'Pause hero slideshow' : 'Play hero slideshow');
            toggle.setAttribute('aria-pressed', playing ? 'true' : 'false');
        }

        function scheduleNext(delay) {
            if (!playing || document.hidden || slides.length < 2) {
                return;
            }

            cancelTimer();
            timer = window.setTimeout(function () {
                show(active + 1);
            }, delay);
        }

        function show(index) {
            cancelTimer();
            active = (index + slides.length) % slides.length;

            slides.forEach(function (slide, position) {
                const selected = position === active;
                slide.classList.toggle('is-active', selected);

                if (selected) {
                    slide.removeAttribute('aria-hidden');
                } else {
                    slide.setAttribute('aria-hidden', 'true');
                }

                const video = slide.querySelector('[data-kc-hero-video]');

                if (video) {
                    video.pause();

                    if (!selected) {
                        video.currentTime = 0;
                    }
                }
            });

            if (count) {
                count.textContent = String(active + 1).padStart(2, '0') + ' / ' + String(slides.length).padStart(2, '0');
            }

            updateToggle();

            if (!playing || document.hidden) {
                return;
            }

            const video = slides[active].querySelector('[data-kc-hero-video]');

            if (!video) {
                scheduleNext(6500);
                return;
            }

            video.muted = true;
            video.currentTime = 0;
            const result = video.play();

            if (result && typeof result.catch === 'function') {
                result.catch(function () {
                    if (slides[active] !== video.closest('[data-kc-hero-slide]')) {
                        return;
                    }

                    if (slides.length === 1) {
                        playing = false;
                        updateToggle();
                    } else {
                        scheduleNext(6500);
                    }
                });
            }
        }

        slides.forEach(function (slide, position) {
            const video = slide.querySelector('[data-kc-hero-video]');

            if (!video) {
                return;
            }

            video.addEventListener('ended', function () {
                if (playing && active === position) {
                    show(active + 1);
                }
            });

            video.addEventListener('error', function () {
                if (playing && active === position) {
                    scheduleNext(6500);
                }
            });
        });

        const previous = hero.querySelector('[data-kc-hero-prev]');
        const next = hero.querySelector('[data-kc-hero-next]');

        if (previous) {
            previous.addEventListener('click', function () {
                show(active - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                show(active + 1);
            });
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                playing = !playing;
                show(active);
            });
        }

        hero.addEventListener('touchstart', function (event) {
            touchStartX = event.touches[0] ? event.touches[0].clientX : null;
        }, { passive: true });

        hero.addEventListener('touchend', function (event) {
            if (touchStartX === null || !event.changedTouches[0] || slides.length < 2) {
                return;
            }

            const distance = event.changedTouches[0].clientX - touchStartX;

            if (Math.abs(distance) > 50) {
                show(active + (distance < 0 ? 1 : -1));
            }

            touchStartX = null;
        }, { passive: true });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                cancelTimer();
                slides.forEach(function (slide) {
                    const video = slide.querySelector('video');

                    if (video) {
                        video.pause();
                    }
                });
            } else {
                show(active);
            }
        });

        show(0);
    });

    document.querySelectorAll('[data-kc-carousel]').forEach(function (carousel) {
        const photos = Array.from(carousel.querySelectorAll('[data-kc-image]'));
        const dots = Array.from(carousel.querySelectorAll('[data-kc-dot]'));
        const counter = carousel.querySelector('[data-kc-count]');
        let active = 0;

        if (photos.length < 2) {
            return;
        }

        function show(index) {
            active = (index + photos.length) % photos.length;
            photos.forEach(function (photo, position) {
                const selected = position === active;
                photo.classList.toggle('is-active', selected);
                if (selected) {
                    photo.removeAttribute('aria-hidden');
                } else {
                    photo.setAttribute('aria-hidden', 'true');
                }
            });
            dots.forEach(function (dot, position) {
                dot.setAttribute('aria-pressed', position === active ? 'true' : 'false');
            });
            if (counter) {
                counter.textContent = String(active + 1).padStart(2, '0') + ' / ' + String(photos.length).padStart(2, '0');
            }
        }

        carousel.querySelectorAll('[data-kc-prev]').forEach(function (button) {
            button.addEventListener('click', function () { show(active - 1); });
        });
        carousel.querySelectorAll('[data-kc-next]').forEach(function (button) {
            button.addEventListener('click', function () { show(active + 1); });
        });
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                show(Number(dot.getAttribute('data-kc-dot')) || 0);
            });
        });

        let touchStartX = null;
        carousel.addEventListener('touchstart', function (event) {
            touchStartX = event.touches[0] ? event.touches[0].clientX : null;
        }, { passive: true });
        carousel.addEventListener('touchend', function (event) {
            if (touchStartX === null || !event.changedTouches[0]) {
                return;
            }
            const distance = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(distance) > 50) {
                show(active + (distance < 0 ? 1 : -1));
            }
            touchStartX = null;
        }, { passive: true });
    });
}());
