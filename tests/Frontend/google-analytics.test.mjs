import test from 'node:test';
import assert from 'node:assert/strict';
import { createMarketingAnalytics, ANALYTICS_CONSENT_KEY } from '../../resources/js/Support/googleAnalytics.js';

const id = 'G-TEST123456';
const origin = 'https://clipperdesk.com';
const paths = ['/', '/features', '/pricing', '/solutions/salons'];
const page = (path = '/', config = {}) => ({ url: `${path}?email=private@example.test#secret`, props: { publicAnalytics: {
    measurement_id: id, paths, page: { location: origin + path, path, title: 'ignored private title' }, ...config,
} } });
function fixture({ stored = null, privacy = {}, storageFailure = false, liveGoogleDrain = false } = {}) {
    let clock = 100000, reloads = 0, timerSequence = 0;
    const scripts = [], commands = [], assignments = [], cookies = [], timers = new Map();
    const storage = new Map(stored === null ? [] : [[ANALYTICS_CONSENT_KEY, stored]]);
    const doc = {
        createElement: () => ({ remove() { this.removed = true; } }), querySelector: () => null,
        head: { append(script) { scripts.push(script); } },
        get cookie() { return 'session=preserve; cd_ga=old; cd_ga_TEST123456=old'; },
        set cookie(value) { cookies.push(value); },
    };
    const browser = {
        navigator: privacy, location: { origin, protocol: 'https:', pathname: '/', assign(url) { assignments.push(url); }, reload() { reloads++; } },
        localStorage: { getItem(key) { if (storageFailure) throw Error(); return storage.get(key) ?? null; }, setItem(key, value) { if (storageFailure) throw Error(); storage.set(key, value); }, removeItem(key) { storage.delete(key); } },
        setTimeout(callback, delay) { const key = ++timerSequence; timers.set(key, { callback, at: clock + delay, delay }); return key; }, clearTimeout(key) { timers.delete(key); },
    };
    const analytics = createMarketingAnalytics({ window: browser, document: doc, now: () => clock });
    function ready(index = scripts.length - 1) {
        const layer = browser.clipperDeskAnalyticsLayer;
        if (layer && !layer.captured) {
            layer.captured = true;
            if (liveGoogleDrain) {
                let cursor = layer.length;
                layer.push = function (command) {
                    Array.prototype.push.call(this, command);
                    // A real tag owns a cursor into this live queue, including while invoking callbacks.
                    while (cursor !== this.length) {
                        const item = this[cursor++];
                        if (!item) throw Error('Google command queue was truncated while its cursor was active');
                        const row = Array.from(item);
                        commands.push(row);
                        if (row[0] === 'event' && typeof row[2]?.event_callback === 'function') row[2].event_callback();
                    }
                    return this.length;
                };
            } else {
                layer.push = function (command) { commands.push(Array.from(command)); return Array.prototype.push.call(this, command); };
            }
        }
        scripts[index].onload();
    }
    return { analytics, scripts, commands, browser, storage, cookies, assignments, timers, ready, get reloads() { return reloads; }, advance(ms) { clock += ms; }, runDue() { for (const [key, timer] of [...timers]) { if (timer.at <= clock) { timers.delete(key); timer.callback(); } } } };
}
const accepted = JSON.stringify({ version: 1, choice: 'accepted', expires_at: 100000 + 1000000 });

test('loads no Google resource or command before opt-in, and rejection preserves essential cookies', () => {
    const f = fixture(); f.analytics.updatePage(page());
    assert.equal(f.analytics.state().visible, true); assert.equal(f.scripts.length, 0);
    assert.equal(f.analytics.choose('rejected'), true); assert.equal(f.scripts.length, 0); assert.equal(f.commands.length, 0);
    assert.equal(f.analytics.state().visible, false);
    assert.ok(f.cookies.every(value => value.startsWith('cd_ga')));
    assert.equal(JSON.parse(f.storage.get(ANALYTICS_CONSENT_KEY)).choice, 'rejected');
});

test('opt-in initializes once with sanitized locations, denied advertising, host-only 60-day cookies and one page view', () => {
    const f = fixture(); f.analytics.updatePage(page()); f.analytics.choose('accepted');
    assert.equal(f.scripts.length, 1); assert.match(f.scripts[0].src, /gtag\/js\?id=G-TEST123456&l=clipperDeskAnalyticsLayer$/);
    assert.equal(f.scripts[0].referrerPolicy, 'no-referrer');
    assert.equal(f.commands.length, 0); f.ready();
    const config = f.commands.find(row => row[0] === 'config')[2];
    assert.equal(config.send_page_view, false); assert.equal(config.allow_google_signals, false);
    assert.equal(config.allow_ad_personalization_signals, false); assert.equal(config.cookie_domain, 'none');
    assert.equal(config.cookie_prefix, 'cd'); assert.equal(config.cookie_expires, 5184000);
    assert.equal(config.cookie_update, false);
    assert.equal(config.page_location, undefined);
    const defaults = f.commands.find(row => row[0] === 'set' && typeof row[1] === 'object')[1];
    assert.equal(defaults.page_referrer, ''); assert.equal(defaults.page_location, origin + '/');
    assert.equal(f.commands.find(row => row[0] === 'consent')[2].ad_user_data, 'denied');
    assert.equal(f.commands.filter(row => row[1] === 'page_view').length, 1);
    assert.ok(!JSON.stringify(f.commands).includes('private')); assert.ok(!JSON.stringify(f.commands).includes('secret'));
    f.analytics.updatePage(page()); assert.equal(f.scripts.length, 1);
    assert.equal(f.commands.filter(row => row[1] === 'page_view').length, 1);
});

test('saved acceptance resumes and Inertia visits count only distinct public pages', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    f.analytics.beforeVisit({ detail: { visit: { method: 'get', url: new URL(origin + '/pricing?name=private') } }, preventDefault() { assert.fail(); } });
    assert.equal(f.browser[`ga-disable-${id}`], true);
    f.analytics.updatePage(page('/pricing')); f.analytics.updatePage(page('/pricing'));
    assert.equal(f.commands.filter(row => row[1] === 'page_view').length, 2);
    assert.ok(!JSON.stringify(f.commands).includes('name='));
});

test('script completion during a public transition waits for the confirmed safe page before configuring', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page());
    f.analytics.beforeVisit({ detail: { visit: { method: 'get', url: origin + '/pricing' } }, preventDefault() { assert.fail(); } });
    f.ready(); assert.equal(f.commands.length, 0);
    f.analytics.updatePage(page('/pricing'));
    assert.equal(f.commands.find(row => row[0] === 'set' && typeof row[1] === 'object')[1].page_location, origin + '/pricing');
    assert.equal(f.commands.filter(row => row[1] === 'page_view').length, 1);
});

test('private, help, auth and booking transitions disable Google and use a fresh document', () => {
    for (const path of ['/login', '/register', '/book/salon', '/appointments/secure/token', '/help/search?q=private', '/businesses/id/app/clients']) {
        const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
        let cancelled = false;
        f.analytics.beforeVisit({ detail: { visit: { method: 'get', url: new URL(origin + path) } }, preventDefault() { cancelled = true; } });
        assert.equal(cancelled, true); assert.deepEqual(f.assignments, [origin + path]); assert.equal(f.browser[`ga-disable-${id}`], true);
        f.analytics.updatePage({ url: path, props: { publicAnalytics: null } });
        assert.equal(f.analytics.state().available, false); assert.equal(f.commands.filter(row => row[1] === 'page_view').length, 1);
    }
});

test('only bounded CTA and explicit pricing interval properties reach Google', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page('/pricing')); f.ready();
    const click = (context, action = 'trial') => f.analytics.click({ preventDefault() {}, target: { closest: () => ({ href: origin + '/register?email=private', dataset: { ctaContext: context, ctaAction: action } }) } });
    f.analytics.marketingEvent({ detail: { name: 'marketing_pricing_interval_changed', properties: { interval: 'annual', email: 'private', path: '/private' } } });
    f.analytics.marketingEvent({ detail: { name: 'marketing_pricing_interval_changed', properties: { interval: 'private' } } });
    click('pricing_hero'); f.analytics.updatePage(page('/pricing')); click('email=private');
    assert.equal(f.commands.filter(row => row[1] === 'marketing_cta_clicked').length, 1);
    assert.equal(f.commands.filter(row => row[1] === 'marketing_pricing_interval_changed').length, 1);
    assert.ok(!JSON.stringify(f.commands).includes('private'));
});

test('withdrawal is persisted before removing the tag, clearing only its cookies and refreshing the document', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    f.analytics.openPreferences(); assert.equal(f.analytics.state().visible, true);
    f.analytics.choose('rejected'); assert.equal(f.browser[`ga-disable-${id}`], true);
    assert.equal(f.scripts[0].removed, true); assert.equal(f.reloads, 1);
    assert.equal(JSON.parse(f.storage.get(ANALYTICS_CONSENT_KEY)).choice, 'rejected');
});

test('browser privacy signals override even previously accepted consent', () => {
    for (const privacy of [{ globalPrivacyControl: true }, { doNotTrack: '1' }, { doNotTrack: 'yes' }]) {
        const f = fixture({ stored: accepted, privacy }); f.analytics.updatePage(page());
        assert.equal(f.analytics.state().choice, 'rejected'); assert.equal(f.scripts.length, 0);
        assert.equal(f.analytics.choose('accepted'), false); assert.equal(f.scripts.length, 0);
        f.analytics.openPreferences(); assert.equal(f.analytics.state().visible, true);
    }
});

test('expired, malformed, wrong-version and implausibly long consent records never activate Google', () => {
    for (const stored of ['broken', 'null', JSON.stringify({ version: 2, choice: 'accepted', expires_at: 1000000 }), JSON.stringify({ version: 1, choice: 'accepted', expires_at: 99999 }), JSON.stringify({ version: 1, choice: 'accepted', expires_at: 1e20 })]) {
        const f = fixture({ stored }); f.analytics.updatePage(page()); assert.equal(f.scripts.length, 0); assert.equal(f.analytics.state().visible, true);
    }
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready(); f.advance(2000000);
    f.analytics.marketingEvent({ detail: { name: 'marketing_pricing_interval_changed', properties: { interval: 'annual' } } });
    assert.equal(f.commands.filter(row => row[1] === 'marketing_pricing_interval_changed').length, 0);
});

test('unavailable storage keeps analytics off and gives an honest saved-choice failure state', () => {
    const f = fixture({ storageFailure: true }); f.analytics.updatePage(page());
    assert.equal(f.analytics.choose('accepted'), false); assert.equal(f.scripts.length, 0);
    assert.equal(f.analytics.state().storageError, true); assert.equal(f.analytics.state().choice, 'rejected');
});

test('malformed stream IDs, foreign or query-bearing canonical URLs and authenticated page payloads fail closed', () => {
    const cases = [page('/', { measurement_id: 'G-<script>' }), page('/', { page: { location: 'https://evil.test/' } }), page('/', { page: { location: origin + '/?email=private' } }), page('/login'), { ...page(), props: { ...page().props, auth: { user: { id: 1 } } } }];
    for (const value of cases) {
        const f = fixture({ stored: accepted }); f.analytics.updatePage(value); assert.equal(f.scripts.length, 0); assert.equal(f.analytics.state().available, false);
    }
});

test('legal pages retain preferences without loading Google or reporting page views', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page('/privacy-policy', { page: null }));
    assert.equal(f.analytics.state().available, true); f.analytics.openPreferences(); assert.equal(f.analytics.state().visible, true);
    assert.equal(f.scripts.length, 0); assert.equal(f.commands.length, 0);
});

test('script network failure and timeout stop automatic retries and ignore late callbacks', () => {
    for (const reason of ['error', 'timeout']) {
        const f = fixture({ stored: accepted }); f.analytics.updatePage(page());
        if (reason === 'error') f.scripts[0].onerror(); else { f.advance(15000); f.runDue(); }
        f.ready(); f.analytics.updatePage(page('/pricing')); f.analytics.choose('accepted');
        assert.equal(f.scripts.length, 1); assert.equal(f.commands.length, 0); assert.equal(f.scripts[0].removed, true);
        assert.equal(f.browser[`ga-disable-${id}`], true);
    }
});

test('cross-tab withdrawal disables a loaded tag immediately', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    f.storage.set(ANALYTICS_CONSENT_KEY, JSON.stringify({ version: 1, choice: 'rejected', expires_at: 1000000 }));
    f.analytics.storageChanged({ key: ANALYTICS_CONSENT_KEY });
    assert.equal(f.browser[`ga-disable-${id}`], true); assert.equal(f.reloads, 1);
});

test('cancelled visits and browser history restoration resume only the matching public page without duplicate views', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    f.analytics.beforeVisit({ detail: { visit: { method: 'get', url: origin + '/pricing' } }, preventDefault() {} });
    assert.equal(f.browser[`ga-disable-${id}`], true);
    f.analytics.resume(); assert.equal(f.browser[`ga-disable-${id}`], false);
    assert.equal(f.commands.filter(row => row[1] === 'page_view').length, 1);
    f.analytics.stop(); f.browser.location.pathname = '/login'; f.analytics.resume();
    assert.equal(f.browser[`ga-disable-${id}`], true);
});


test('automatic-event defaults follow sanitized public navigation without initial stream overrides', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    f.analytics.updatePage(page('/pricing'));
    const defaults = f.commands.filter(row => row[0] === 'set' && typeof row[1] === 'object').at(-1)[1];
    assert.deepEqual(defaults, { page_location: origin + '/pricing', page_referrer: '', page_title: 'ClipperDesk' });
    assert.equal(f.commands.filter(row => row[0] === 'config').length, 1);
    assert.equal(f.commands.find(row => row[0] === 'config')[2].page_location, undefined);
});

test('a still-open loaded document disables collection and reloads when accepted consent expires', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    f.advance(1000001); f.runDue();
    assert.equal(f.browser[`ga-disable-${id}`], true);
    assert.equal(f.analytics.state().choice, null); assert.equal(f.analytics.state().visible, true);
    assert.equal(f.storage.has(ANALYTICS_CONSENT_KEY), false); assert.equal(f.reloads, 1);
    assert.equal(f.timers.size, 0);
});

test('consent expiry checks use bounded timers and keep a renewed choice active', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    const renewed = { version: 1, choice: 'accepted', expires_at: 100000 + 2 * 86400000 };
    f.storage.set(ANALYTICS_CONSENT_KEY, JSON.stringify(renewed));
    f.advance(1000001); f.runDue();
    assert.equal(f.reloads, 0); assert.equal(f.analytics.state().choice, 'accepted');
    assert.ok([...f.timers.values()].every(timer => timer.delay <= 86400000));
});

function trialClick(fixture, options = {}) {
    let prevented = false;
    const { href = origin + '/register?locations=2&staff=3', context = 'header', target = '', download = false, ...keys } = options;
    fixture.analytics.click({
        ...keys, preventDefault() { prevented = true; },
        target: { closest: () => ({ href, target, hasAttribute: name => name === 'download' && download,
            dataset: { ctaContext: context, ctaAction: 'trial' } }) },
    });
    return prevented;
}

test('a consenting trial click keeps Google enabled only until processing completes then navigates once to the original signup selection', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    assert.equal(trialClick(f), true);
    assert.equal(f.browser[`ga-disable-${id}`], false); assert.deepEqual(f.assignments, []);
    const event = f.commands.find(row => row[1] === 'marketing_cta_clicked')[2];
    assert.equal(event.event_timeout, 200); assert.equal(event.page_location, origin + '/');
    assert.equal(event.page_referrer, ''); assert.equal(event.cta_context, 'header');
    assert.ok(!JSON.stringify(event).includes('locations='));
    event.event_callback(); event.event_callback(); f.advance(500); f.runDue();
    assert.deepEqual(f.assignments, [origin + '/register?locations=2&staff=3']);
    assert.equal(f.browser[`ga-disable-${id}`], true); assert.equal(f.timers.size, 0);
});

test('a missing Google callback delays trial navigation at most 200ms and cannot block or duplicate it', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
    trialClick(f); assert.equal(trialClick(f), true);
    assert.equal(f.commands.filter(row => row[1] === 'marketing_cta_clicked').length, 1);
    f.advance(199); f.runDue(); assert.equal(f.assignments.length, 0);
    f.advance(1); f.runDue(); assert.equal(f.assignments.length, 1);
    f.commands.find(row => row[1] === 'marketing_cta_clicked')[2].event_callback();
    assert.equal(f.assignments.length, 1); assert.equal(f.browser[`ga-disable-${id}`], true);
});

test('withdrawal during the short CTA wait saves rejection then immediately finishes the requested signup navigation', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready(); trialClick(f);
    f.analytics.choose('rejected');
    assert.equal(JSON.parse(f.storage.get(ANALYTICS_CONSENT_KEY)).choice, 'rejected');
    assert.equal(f.assignments.length, 1); assert.equal(f.reloads, 0);
    assert.equal(f.browser[`ga-disable-${id}`], true); assert.equal(f.scripts[0].removed, true);
    f.advance(200); f.runDue(); assert.equal(f.assignments.length, 1);
});

test('modified, new-tab, download and unrecognized trial links retain native navigation without a delay', () => {
    for (const options of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { target: '_blank' }, { download: true }, { context: 'unknown' }, { href: 'https://other.test/register' }]) {
        const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready();
        assert.equal(trialClick(f, options), false); assert.equal(f.assignments.length, 0);
        assert.ok(!f.commands.some(row => row[1] === 'marketing_cta_clicked' && row[2].event_callback));
    }
    for (const stored of [null, JSON.stringify({ version: 1, choice: 'rejected', expires_at: 1000000 })]) {
        const f = fixture({ stored }); f.analytics.updatePage(page());
        assert.equal(trialClick(f), false); assert.equal(f.assignments.length, 0); assert.equal(f.commands.length, 0);
    }
    const loading = fixture({ stored: accepted }); loading.analytics.updatePage(page());
    assert.equal(trialClick(loading), false); assert.equal(loading.assignments.length, 0);
});

test('another navigation cancels a deferred CTA so its late callback never hijacks the next destination', () => {
    const f = fixture({ stored: accepted }); f.analytics.updatePage(page()); f.ready(); trialClick(f);
    const callback = f.commands.find(row => row[1] === 'marketing_cta_clicked')[2].event_callback;
    f.analytics.beforeVisit({ detail: { visit: { method: 'get', url: origin + '/features' } }, preventDefault() { assert.fail(); } });
    callback(); f.advance(200); f.runDue(); assert.equal(f.assignments.length, 0);
    f.analytics.updatePage(page('/features')); assert.equal(f.browser[`ga-disable-${id}`], false);
});

test('a synchronous live Google queue callback completes trial navigation without truncating the array Google is processing', () => {
    const f = fixture({ stored: accepted, liveGoogleDrain: true }); f.analytics.updatePage(page()); f.ready();
    assert.doesNotThrow(() => trialClick(f));
    assert.deepEqual(f.assignments, [origin + '/register?locations=2&staff=3']);
    assert.equal(f.browser[`ga-disable-${id}`], true);
    assert.equal(f.commands.filter(row => row[1] === 'marketing_cta_clicked').length, 1);
    assert.ok(f.browser.clipperDeskAnalyticsLayer.length > 0);
    f.advance(200); f.runDue(); assert.equal(f.assignments.length, 1);
});

test('withdrawal preserves Googles owned queue while disabling collection and reloading the document', () => {
    const f = fixture({ stored: accepted, liveGoogleDrain: true }); f.analytics.updatePage(page()); f.ready();
    const length = f.browser.clipperDeskAnalyticsLayer.length;
    f.analytics.choose('rejected');
    assert.equal(f.browser.clipperDeskAnalyticsLayer.length, length);
    assert.equal(f.browser[`ga-disable-${id}`], true); assert.equal(f.reloads, 1);
});
