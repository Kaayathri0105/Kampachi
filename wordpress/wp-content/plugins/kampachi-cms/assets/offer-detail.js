(function () {
    'use strict';

    document.querySelectorAll('[data-offer-carousel]').forEach(function (root) {
        const slides = Array.from(root.querySelectorAll('[data-offer-slide]'));
        if (!slides.length) return;

        const pause = root.querySelector('[data-offer-pause]');
        const previous = root.querySelector('[data-offer-previous]');
        const next = root.querySelector('[data-offer-next]');
        const counter = root.querySelector('[data-offer-counter]');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let index = 0;
        let paused = motion.matches;
        let hovering = false;
        let focused = false;
        let timer;
        let generation = 0;

        function stopTimer() {
            window.clearTimeout(timer);
        }

        function canAdvance() {
            return !paused && !hovering && !focused && !document.hidden;
        }

        function updatePause() {
            root.classList.toggle('is-paused', paused);
            if (!pause) return;
            pause.textContent = paused ? 'Play' : 'Pause';
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? 'Play banners' : 'Pause banners');
        }

        function queueNext() {
            stopTimer();
            if (slides.length > 1 && canAdvance()) timer = window.setTimeout(function () { show(index + 1); }, 6500);
        }

        function playCurrent() {
            stopTimer();
            if (paused || document.hidden) return;
            const video = slides[index].querySelector('video');
            if (!video) {
                queueNext();
                return;
            }
            if (video.ended && slides.length > 1) {
                if (canAdvance()) show(index + 1);
                return;
            }
            const request = generation;
            const result = video.play();
            if (result && typeof result.then === 'function') {
                result.then(function () {
                    if (slides[index].querySelector('video') !== video || paused || document.hidden) video.pause();
                }).catch(function () {
                    if (request === generation) queueNext();
                });
            }
        }

        function show(target) {
            stopTimer();
            generation += 1;
            index = ((target % slides.length) + slides.length) % slides.length;
            slides.forEach(function (slide, position) {
                const active = position === index;
                slide.hidden = !active;
                slide.classList.toggle('is-active', active);
                const video = slide.querySelector('video');
                if (video && !active) {
                    video.pause();
                    if (video.readyState > 0) video.currentTime = 0;
                }
            });
            if (counter) counter.textContent = (index + 1) + ' / ' + slides.length;
            playCurrent();
        }

        if (previous) previous.addEventListener('click', function () { show(index - 1); });
        if (next) next.addEventListener('click', function () { show(index + 1); });
        if (pause) pause.addEventListener('click', function () {
            paused = !paused;
            generation += 1;
            stopTimer();
            const video = slides[index].querySelector('video');
            if (paused && video) video.pause();
            updatePause();
            if (!paused) playCurrent();
        });
        root.addEventListener('pointerenter', function () { hovering = true; stopTimer(); });
        root.addEventListener('pointerleave', function () { hovering = false; playCurrent(); });
        root.addEventListener('focusin', function () { focused = true; stopTimer(); });
        root.addEventListener('focusout', function (event) {
            if (event.relatedTarget && root.contains(event.relatedTarget)) return;
            focused = false;
            playCurrent();
        });
        root.addEventListener('keydown', function (event) {
            if (event.target.closest('video, button, a, input, textarea, select')) return;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                show(index + (event.key === 'ArrowLeft' ? -1 : 1));
            }
        });
        slides.forEach(function (slide, position) {
            const video = slide.querySelector('video');
            if (!video) return;
            video.muted = true;
            video.addEventListener('ended', function () { if (position === index && canAdvance() && slides.length > 1) show(index + 1); });
            video.addEventListener('error', function () { if (position === index) queueNext(); });
            const source = video.querySelector('source');
            if (source) source.addEventListener('error', function () { if (position === index) queueNext(); });
        });
        document.addEventListener('visibilitychange', function () {
            generation += 1;
            stopTimer();
            const video = slides[index].querySelector('video');
            if (document.hidden && video) video.pause();
            if (!document.hidden) playCurrent();
        });
        function updateMotion(event) {
            paused = event.matches;
            generation += 1;
            const video = slides[index].querySelector('video');
            if (paused && video) video.pause();
            updatePause();
            if (paused) stopTimer(); else playCurrent();
        }
        if (motion.addEventListener) motion.addEventListener('change', updateMotion);
        else if (motion.addListener) motion.addListener(updateMotion);

        updatePause();
        show(0);
    });
})();
