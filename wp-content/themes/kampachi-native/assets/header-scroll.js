(() => {
    if (window.kampachiHeaderScrollLoaded) {
        return;
    }
    window.kampachiHeaderScrollLoaded = true;
    const header = document.querySelector('.site-header');
    const navigation = document.querySelector('.menu-overlay');

    if (!header) {
        return;
    }

    let frameRequested = false;

    const updateHeaderState = () => {
        const isScrolled = window.scrollY > 12;
        header.classList.toggle('is-scrolled', isScrolled);

        if (navigation) {
            navigation.classList.toggle('is-scrolled', isScrolled);
        }
    };

    const handleScroll = () => {
        if (frameRequested) {
            return;
        }

        frameRequested = true;
        window.requestAnimationFrame(() => {
            updateHeaderState();
            frameRequested = false;
        });
    };

    updateHeaderState();
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('pageshow', updateHeaderState);
})();
