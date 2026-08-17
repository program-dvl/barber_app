const installedKey = '__goodHoursMarketingTelemetryInstalled';

export const emitMarketingEvent = (name, properties = {}) => {
    window.dispatchEvent(new CustomEvent('good-hours:marketing', {
        detail: { name, properties: { ...properties, path: window.location.pathname } },
    }));
};

export const installMarketingTelemetryContract = () => {
    if (typeof window === 'undefined' || window[installedKey]) return;
    window[installedKey] = true;
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('[data-cta-context]');
        if (!link) return;
        emitMarketingEvent('marketing_cta_clicked', {
            context: String(link.dataset.ctaContext ?? '').slice(0, 80),
            action: String(link.dataset.ctaAction ?? '').slice(0, 32),
        });
    }, { passive: true });
};
