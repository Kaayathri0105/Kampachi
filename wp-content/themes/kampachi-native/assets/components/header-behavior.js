(() => {
    if (window.kampachiHeaderBehaviourLoaded) {
        return;
    }
    window.kampachiHeaderBehaviourLoaded = true;
    const menuButton = document.querySelector('.menu-button');
    const menuOverlay = document.querySelector('.menu-overlay');
    const desktopNavQuery = window.matchMedia('(min-width: 1200px)');
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    const languageWrap = document.querySelector('.language-wrap');
    const languageButton = document.querySelector('.language-button');
    const languageFlag = document.querySelector('.language-flag');
    const languageOptions = document.querySelectorAll('.language-option');
    const reservationWrap = document.querySelector('.reservation-wrap');
    const reservationTrigger = document.querySelector('.reservation-trigger');
    const reservationMenu = document.querySelector('.reservation-menu');

    const setDropdownOpen = (toggle, isOpen) => {
        const submenu = document.getElementById(toggle.getAttribute('aria-controls'));

        if (!submenu) {
            return;
        }

        const menuLabel = toggle.dataset.menuLabel || 'submenu';

        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', `${isOpen ? 'Hide' : 'Show'} ${menuLabel} submenu`);
        toggle.closest('.nav-item--dropdown')?.classList.toggle('is-expanded', isOpen);
        submenu.hidden = !isOpen;
    };

    const closeDropdowns = (exceptToggle = null) => {
        dropdownToggles.forEach((toggle) => {
            if (toggle !== exceptToggle) {
                setDropdownOpen(toggle, false);
            }
        });
    };

    const setMenuOpen = (isOpen) => {
        if (!menuButton || !menuOverlay) {
            return;
        }

        if (desktopNavQuery.matches) {
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Open navigation menu');
            menuOverlay.classList.remove('is-open');
            menuOverlay.setAttribute('aria-hidden', 'false');
            menuOverlay.inert = false;
            document.body.classList.remove('menu-open');
            closeDropdowns();
            return;
        }

        menuButton.setAttribute('aria-expanded', String(isOpen));
        menuButton.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        menuOverlay.classList.toggle('is-open', isOpen);
        menuOverlay.setAttribute('aria-hidden', String(!isOpen));
        menuOverlay.inert = !isOpen;
        document.body.classList.toggle('menu-open', isOpen);

        if (isOpen) {
            closeReservationMenu();
            closeLanguageMenu();
        } else {
            closeDropdowns();
        }
    };

    const closeLanguageMenu = () => {
        if (!languageWrap || !languageButton) {
            return;
        }

        languageWrap.classList.remove('is-open');
        languageButton.setAttribute('aria-expanded', 'false');
    };

    const setLanguageMenuOpen = (isOpen) => {
        if (!languageWrap || !languageButton) {
            return;
        }

        languageWrap.classList.toggle('is-open', isOpen);
        languageButton.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            closeReservationMenu();
            setMenuOpen(false);
        }
    };

    const closeReservationMenu = () => {
        if (!reservationWrap || !reservationTrigger || !reservationMenu) {
            return;
        }

        reservationWrap.classList.remove('is-open');
        reservationTrigger.setAttribute('aria-expanded', 'false');
        reservationMenu.setAttribute('aria-hidden', 'true');
        reservationMenu.inert = true;
        reservationMenu.hidden = true;
    };

    const setReservationMenuOpen = (isOpen) => {
        if (!reservationWrap || !reservationTrigger || !reservationMenu) {
            return;
        }

        reservationWrap.classList.toggle('is-open', isOpen);
        reservationTrigger.setAttribute('aria-expanded', String(isOpen));
        reservationMenu.setAttribute('aria-hidden', String(!isOpen));
        reservationMenu.inert = !isOpen;
        reservationMenu.hidden = !isOpen;

        if (isOpen) {
            closeLanguageMenu();
        }
    };

    setMenuOpen(false);
    closeLanguageMenu();
    closeReservationMenu();

    desktopNavQuery.addEventListener('change', () => setMenuOpen(false));

    menuButton?.addEventListener('click', () => {
        setMenuOpen(menuButton.getAttribute('aria-expanded') !== 'true');
    });

    menuOverlay?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setMenuOpen(false));
    });

    dropdownToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            closeDropdowns(toggle);
            setDropdownOpen(toggle, !isOpen);
        });
    });

    languageButton?.addEventListener('click', () => {
        setLanguageMenuOpen(!languageWrap.classList.contains('is-open'));
    });

    languageOptions.forEach((option) => {
        option.addEventListener('click', () => {
            if (languageFlag) {
                languageFlag.textContent = option.dataset.flag || '🇲🇾';
            }

            languageButton?.setAttribute('aria-label', `Language: ${option.textContent.trim()}`);
            languageOptions.forEach((item) => item.removeAttribute('aria-current'));
            option.setAttribute('aria-current', 'true');
            closeLanguageMenu();
        });
    });

    reservationTrigger?.addEventListener('click', () => {
        const isOpen = reservationTrigger.getAttribute('aria-expanded') === 'true';

        setReservationMenuOpen(!isOpen);
    });

    document.addEventListener('click', (event) => {
        if (languageWrap && !languageWrap.contains(event.target)) {
            closeLanguageMenu();
        }

        if (reservationWrap && !reservationWrap.contains(event.target)) {
            closeReservationMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMenuOpen(false);
            closeLanguageMenu();
            closeReservationMenu();
        }
    });
})();
