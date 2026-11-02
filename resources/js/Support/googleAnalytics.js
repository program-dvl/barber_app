export const ANALYTICS_CONSENT_KEY = 'clipperdesk:analytics-consent:v1';
export const ANALYTICS_CONSENT_DAYS = 180;
const day = 24 * 60 * 60 * 1000;
const layerName = 'clipperDeskAnalyticsLayer';
const legalPaths = new Set(['/privacy-policy', '/terms-of-service', '/refund-policy']);
const publicPath = /^(?:\/|\/(?:features|solutions|use-cases)(?:\/[a-z0-9-]+)?|\/guides\/[a-z0-9-]+|\/(?:pricing|company|security|resources|blog))$/;

// The injectable browser boundary lets tests exercise privacy without contacting Google.
export function createMarketingAnalytics({ window: browser, document: doc, now = Date.now } = {}) {
    let config = null, consent = null, opened = false, storageError = false;
    let suspended = false, attempted = false, loaded = false, initialized = false, failed = false, script = null, timer = null, lastPage = null;
    let id = null, preferenceOpener = null, expiryTimer = null, pendingTrial = null;
    const listeners = new Set();
    const privacySignal = () => browser.navigator?.globalPrivacyControl === true
        || ['1', 'yes'].includes(String(browser.navigator?.doNotTrack ?? browser.doNotTrack ?? '').toLowerCase());
    const validConsent = record => record?.version === 1 && ['accepted', 'rejected'].includes(record.choice)
        && Number.isFinite(record.expires_at) && record.expires_at > now()
        && record.expires_at <= now() + ANALYTICS_CONSENT_DAYS * day;
    function readConsent() {
        try {
            const record = JSON.parse(browser.localStorage.getItem(ANALYTICS_CONSENT_KEY));
            return validConsent(record) ? record : null;
        } catch { return null; }
    }
    consent = readConsent();
    const accepted = () => validConsent(consent) && consent.choice === 'accepted' && !privacySignal();
    const canMeasure = () => Boolean(config?.page && accepted() && !suspended);
    const state = () => ({
        available: Boolean(config), visible: Boolean(config && (opened || (!validConsent(consent) && !privacySignal()))),
        choice: privacySignal() ? 'rejected' : validConsent(consent) ? consent.choice : null,
        privacySignal: privacySignal(), storageError,
        preferencesOpen: opened,
    });
    const notify = () => listeners.forEach(listener => listener(state()));
    function clearCookies() {
        for (const pair of (doc.cookie || '').split(';')) {
            const name = pair.split('=')[0].trim();
            if (/^cd_ga(?:_|$)/.test(name)) doc.cookie = `${name}=; Max-Age=0; Path=/; SameSite=Lax; Secure`;
        }
    }
    function disable() {
        if (id) browser[`ga-disable-${id}`] = true;
        if (browser[layerName]) browser[layerName].length = 0;
        if (timer !== null) browser.clearTimeout(timer);
        if (expiryTimer !== null) browser.clearTimeout(expiryTimer);
        expiryTimer = null;
        timer = null;
    }
    function expireConsent() {
        // Another tab may have renewed the choice while this document was asleep.
        const current = readConsent();
        if (current) { consent = current; start(); return; }
        disable();
        consent = null;
        opened = false;
        clearCookies();
        try { browser.localStorage.removeItem(ANALYTICS_CONSENT_KEY); } catch { /* Collection still stays off. */ }
        notify();
        if (pendingTrial) pendingTrial.finish();
        else if (attempted) browser.location.reload();
    }
    function armConsentExpiry() {
        if (expiryTimer !== null) browser.clearTimeout(expiryTimer);
        expiryTimer = null;
        if (!config || !consent) return;
        const remaining = consent.expires_at - now();
        if (remaining <= 0) { expireConsent(); return; }
        // Daily bounds avoid the browser's 32-bit timeout limit and recheck sleeping tabs.
        expiryTimer = browser.setTimeout(() => {
            expiryTimer = null;
            if (consent && consent.expires_at <= now()) expireConsent();
            else armConsentExpiry();
        }, Math.min(day, remaining));
    }
    function command() {
        if (canMeasure()) browser[layerName].push(arguments);
    }
    function pageView() {
        if (!loaded || !canMeasure() || lastPage === config.page.location) return;
        lastPage = config.page.location;
        command('set', currentPageFields());
        command('event', 'page_view', pageParameters());
    }
    function pageParameters() {
        return { send_to: id, ...currentPageFields() };
    }
    function currentPageFields() {
        return { page_location: config.page.location, page_referrer: '', page_title: 'ClipperDesk' };
    }
    function start() {
        if (failed || !canMeasure()) { disable(); armConsentExpiry(); return; }
        armConsentExpiry();
        browser[`ga-disable-${id}`] = false;
        if (loaded) {
            if (!initialized) {
                initialized = true;
                command('consent', 'default', { analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
                command('set', 'ads_data_redaction', true);
                command('set', 'url_passthrough', false);
                // Set defaults before config; config-scoped page fields would pin automatic events to the first page.
                command('set', currentPageFields());
                command('js', new Date(now()));
                command('config', id, {
                    send_page_view: false,
                    allow_google_signals: false, allow_ad_personalization_signals: false,
                    cookie_domain: 'none', cookie_prefix: 'cd', cookie_expires: 60 * 24 * 60 * 60,
                    cookie_update: false, cookie_flags: 'SameSite=Lax;Secure', ignore_referrer: true,
                });
            }
            pageView(); return;
        }
        if (attempted) return;
        attempted = true;
        browser[layerName] = [];
        script = doc.createElement('script');
        script.async = true;
        script.referrerPolicy = 'no-referrer';
        script.src = `https://www.googletagmanager.com/gtag/js?id=${id}&l=${layerName}`;
        const nonce = doc.querySelector('script[nonce]')?.nonce;
        if (nonce) script.nonce = nonce;
        const fail = () => { failed = true; disable(); armConsentExpiry(); script?.remove(); };
        script.onerror = fail;
        script.onload = () => {
            if (failed) return;
            if (timer !== null) browser.clearTimeout(timer);
            timer = null;
            loaded = true;
            start();
        };
        timer = browser.setTimeout(fail, 15000);
        doc.head.append(script);
    }
    function safeConfig(page) {
        const value = page?.props?.publicAnalytics;
        if (!value || page.props.auth?.user || !/^G-[A-Z0-9]{6,20}$/.test(value.measurement_id || '')
            || browser.location.protocol !== 'https:') return null;
        const paths = Array.isArray(value.paths) ? value.paths.filter(path => publicPath.test(path)) : [];
        try {
            const actual = new URL(page.url, browser.location.origin);
            if (actual.origin !== browser.location.origin) return null;
            if (!value.page) return legalPaths.has(actual.pathname) ? { ...value, paths, page: null } : null;
            const url = new URL(value.page.location);
            if (url.origin !== browser.location.origin || url.search || url.hash || url.username || url.password
                || !paths.includes(url.pathname) || actual.pathname !== url.pathname) return null;
            return { measurement_id: value.measurement_id, paths, page: { location: url.href, path: url.pathname } };
        } catch { return null; }
    }
    function updatePage(page) {
        pendingTrial?.cancel();
        disable();
        const next = safeConfig(page);
        // Changing streams requires a new document; an old tag must never acquire another stream.
        config = id && next && next.measurement_id !== id ? null : next;
        if (config) id = config.measurement_id;
        suspended = false;
        if (!accepted()) clearCookies();
        start();
        notify();
    }
    function choose(choice) {
        if (!config || !['accepted', 'rejected'].includes(choice) || (choice === 'accepted' && privacySignal())) return false;
        disable();
        const record = { version: 1, choice, expires_at: now() + ANALYTICS_CONSENT_DAYS * day };
        try {
            browser.localStorage.setItem(ANALYTICS_CONSENT_KEY, JSON.stringify(record));
            if (browser.localStorage.getItem(ANALYTICS_CONSENT_KEY) !== JSON.stringify(record)) throw Error();
            consent = record;
            storageError = false;
            opened = false;
            preferenceOpener?.focus?.({ preventScroll: true });
            preferenceOpener = null;
        } catch {
            consent = { ...record, choice: 'rejected' };
            storageError = true;
            opened = true;
        }
        if (!accepted()) {
            clearCookies();
            script?.remove();
            armConsentExpiry();
            notify();
            // Remove Google's document listeners after withdrawal, with the rejection already saved.
            if (pendingTrial) pendingTrial.finish();
            else if (attempted) browser.location.reload();
        } else start();
        notify();
        return !storageError;
    }
    function publicDestination(url) {
        try {
            const target = new URL(url, browser.location.origin);
            return target.origin === browser.location.origin && config?.paths.includes(target.pathname);
        } catch { return false; }
    }
    function beforeVisit(event) {
        pendingTrial?.cancel();
        suspended = true;
        disable();
        const visit = event.detail.visit;
        if (attempted && String(visit.method).toLowerCase() === 'get' && !publicDestination(visit.url)) {
            event.preventDefault();
            browser.location.assign(String(visit.url));
        }
    }
    function click(event) {
        const link = event.target?.closest?.('a');
        if (!link || event.defaultPrevented || !canMeasure()) return;
        const leavesDocument = !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey
            && (event.button === undefined || event.button === 0)
            && (!link.target || link.target === '_self') && !link.hasAttribute?.('download');
        let trialDestination = null;
        try {
            const url = new URL(link.href, browser.location.origin);
            if (url.origin === browser.location.origin && url.pathname === '/register' && !url.username && !url.password) trialDestination = url.href;
        } catch { /* Leave malformed links to their normal browser behavior. */ }
        if (pendingTrial && leavesDocument) {
            if (trialDestination === pendingTrial.destination) { event.preventDefault(); return; }
            pendingTrial.cancel();
        }
        const context = link.dataset?.ctaContext;
        const contexts = new Set(['header', 'mobile_navigation', 'homepage_hero', 'homepage_plan_teaser', 'homepage_final', 'pricing_hero', 'pricing_capacity', 'company_final', 'security_final', 'features_hub_final', 'solutions_hub_final', 'use_cases_hub_final']);
        for (const path of config.paths) {
            const [, group, slug] = path.split('/');
            if (slug && ['features', 'solutions', 'use-cases'].includes(group)) {
                const prefix = { features: 'feature', solutions: 'solution', 'use-cases': 'use_case' }[group];
                contexts.add(`${prefix}_${slug}_final`);
                if (group === 'solutions') contexts.add(`${prefix}_${slug}_hero`);
            }
        }
        if (contexts.has(context) && link.dataset.ctaAction === 'trial' && loaded) {
            const parameters = { ...pageParameters(), cta_context: context, cta_action: 'trial' };
            if (leavesDocument && trialDestination) {
                // Keep this public document active only while Google processes the bounded click.
                // The independent deadline preserves navigation even when a blocker drops its callback.
                event.preventDefault();
                let finished = false, deadline = null;
                const cancel = () => {
                    if (finished) return;
                    finished = true;
                    if (deadline !== null) browser.clearTimeout(deadline);
                    pendingTrial = null;
                };
                const finish = () => {
                    if (finished) return;
                    cancel();
                    suspended = true;
                    disable();
                    browser.location.assign(trialDestination);
                };
                pendingTrial = { destination: trialDestination, finish, cancel };
                deadline = browser.setTimeout(finish, 200);
                command('event', 'marketing_cta_clicked', { ...parameters, event_callback: finish, event_timeout: 200 });
                return;
            }
            command('event', 'marketing_cta_clicked', parameters);
        }
        if (attempted && leavesDocument && !publicDestination(link.href)) { suspended = true; disable(); }
    }
    function marketingEvent(event) {
        if (!loaded || pendingTrial || !canMeasure() || config.page.path !== '/pricing') return;
        const detail = event.detail;
        if (detail?.name === 'marketing_pricing_interval_changed' && ['monthly', 'annual'].includes(detail.properties?.interval)) {
            command('event', 'marketing_pricing_interval_changed', { ...pageParameters(), interval: detail.properties.interval });
        }
    }
    function storageChanged(event) {
        if (event.key !== ANALYTICS_CONSENT_KEY) return;
        consent = readConsent();
        if (!accepted()) {
            disable(); clearCookies();
            armConsentExpiry();
            if (pendingTrial) pendingTrial.finish();
            else if (attempted) browser.location.reload();
        } else start();
        notify();
    }
    function resume() {
        if (pendingTrial) return;
        if (!config?.page || browser.location.pathname !== config.page.path) { disable(); return; }
        suspended = false;
        start();
        notify();
    }
    return {
        updatePage, choose, beforeVisit, click, marketingEvent, storageChanged, resume,
        state, subscribe(listener) { listeners.add(listener); listener(state()); return () => listeners.delete(listener); },
        openPreferences() { if (config) { preferenceOpener = doc.activeElement; opened = true; notify(); } },
        closePreferences() { opened = false; storageError = false; notify(); preferenceOpener?.focus?.({ preventScroll: true }); preferenceOpener = null; },
        stop() { pendingTrial?.cancel(); disable(); },
    };
}

let singleton;
export function getMarketingAnalytics(environment) {
    if (!singleton && (environment || typeof window !== 'undefined')) {
        singleton = createMarketingAnalytics(environment || { window, document });
    }
    return singleton;
}

export function installMarketingAnalytics(router, initialPage) {
    const analytics = getMarketingAnalytics();
    if (!analytics) return;
    analytics.updatePage(initialPage);
    router.on('before', event => analytics.beforeVisit(event));
    router.on('navigate', event => analytics.updatePage(event.detail.page));
    router.on('finish', () => analytics.resume());
    document.addEventListener('click', event => analytics.click(event), { capture: true });
    window.addEventListener('clipperdesk:marketing', event => analytics.marketingEvent(event));
    window.addEventListener('storage', event => analytics.storageChanged(event));
    window.addEventListener('pagehide', () => analytics.stop());
    window.addEventListener('pageshow', event => { if (event.persisted) analytics.resume(); });
    document.addEventListener('visibilitychange', () => analytics.resume());
}
