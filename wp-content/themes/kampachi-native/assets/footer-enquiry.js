const footerEnquiry = document.querySelector('.kc-footer-enquiry');

if (footerEnquiry) {
    const toggle = footerEnquiry.querySelector('summary');
    const panel = footerEnquiry.querySelector('.kc-footer-enquiry-panel');

    footerEnquiry.addEventListener('toggle', () => {
        if (footerEnquiry.open) {
            panel.scrollIntoView({ block: 'nearest' });
        }
    });

    footerEnquiry.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && footerEnquiry.open) {
            footerEnquiry.open = false;
            toggle.focus({ preventScroll: true });
            toggle.scrollIntoView({ block: 'nearest' });
        }
    });
}
