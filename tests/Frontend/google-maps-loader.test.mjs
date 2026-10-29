import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

let moduleSequence = 0;
async function fixture({ key = 'synthetic-browser-key', previousFailure } = {}) {
    const scripts = [];
    globalThis.window = previousFailure ? { gm_authFailure: previousFailure } : {};
    globalThis.document = {
        createElement: () => ({ removed: false, remove() { this.removed = true; } }),
        querySelector: () => ({ nonce: 'synthetic-nonce' }),
        head: { append: script => scripts.push(script) },
    };
    const source = (await readFile(new URL('../../resources/js/Support/googleMaps.js', import.meta.url), 'utf8'))
        .replaceAll('import.meta.env.VITE_GOOGLE_MAPS_API_KEY', JSON.stringify(key))
        .replaceAll('import.meta.env.VITE_GOOGLE_MAPS_MAP_ID', 'undefined');
    const loader = await import(`data:text/javascript;base64,${Buffer.from(`${source}\n// loader fixture ${++moduleSequence}`).toString('base64')}`);
    return { loader, scripts, ready() { window.google = { maps: { importLibrary() {} } }; window.clipperdeskMapsReady(); } };
}

test('concurrent map requests share one script and validate the loaded API', async () => {
    const { loader, scripts, ready } = await fixture();
    const first = loader.loadGoogleMaps(), second = loader.loadGoogleMaps();
    assert.equal(first, second); assert.equal(scripts.length, 1);
    assert.equal(scripts[0].nonce, 'synthetic-nonce');
    ready();
    assert.equal(await first, window.google.maps); assert.equal(await loader.loadGoogleMaps(), window.google.maps);
    assert.equal(scripts.length, 1); assert.equal(window.clipperdeskMapsReady, undefined);
});

test('missing configuration fails safely without inserting a script or echoing credentials', async () => {
    const { loader, scripts } = await fixture({ key: '' });
    await assert.rejects(loader.loadGoogleMaps(), error => error.code === 'MAPS_CONFIGURATION_MISSING' && !error.message.includes('key'));
    assert.equal(scripts.length, 0);
});

test('authentication failure rejects pending work immediately and stops futile reloads', async () => {
    let previousCalls = 0;
    const { loader, scripts } = await fixture({ previousFailure: () => { previousCalls++; throw Error('Private provider text must not escape'); } });
    const codes = [];
    loader.onGoogleMapsFailure(() => { throw Error('Consumer failure'); });
    const stop = loader.onGoogleMapsFailure(code => codes.push(code));
    const pending = loader.loadGoogleMaps(), rejection = assert.rejects(pending, { code: 'MAPS_AUTHENTICATION_FAILED' });
    window.gm_authFailure(); await rejection;
    assert.equal(previousCalls, 1); assert.deepEqual(codes, ['MAPS_AUTHENTICATION_FAILED']); assert.equal(scripts[0].removed, true);
    window.google = { maps: { importLibrary() {} } };
    await assert.rejects(loader.loadGoogleMaps(), { code: 'MAPS_AUTHENTICATION_FAILED' });
    const laterCodes = []; loader.onGoogleMapsFailure(code => laterCodes.push(code));
    assert.deepEqual(laterCodes, ['MAPS_AUTHENTICATION_FAILED']); assert.equal(scripts.length, 1);
    stop(); window.gm_authFailure(); assert.equal(codes.length, 1);
});

test('authentication failure after ready prevents reusing a denied loaded API', async () => {
    const { loader, scripts, ready } = await fixture();
    const pending = loader.loadGoogleMaps(); ready(); await pending;
    window.gm_authFailure();
    await assert.rejects(loader.loadGoogleMaps(), { code: 'MAPS_AUTHENTICATION_FAILED' });
    assert.equal(scripts.length, 1);
});

test('network failure permits one explicit retry and ignores old failure and ready callbacks', async () => {
    const { loader, scripts, ready } = await fixture();
    const first = loader.loadGoogleMaps(), oldFailure = scripts[0].onerror, oldReady = window.clipperdeskMapsReady;
    const rejected = assert.rejects(first, { code: 'MAPS_LOAD_FAILED' }); oldFailure(); await rejected;
    const second = loader.loadGoogleMaps(); assert.equal(scripts.length, 2);
    const currentReady = window.clipperdeskMapsReady;
    oldFailure(); oldReady();
    assert.equal(window.clipperdeskMapsReady, currentReady); assert.equal(scripts[1].removed, false);
    ready(); assert.equal(await second, window.google.maps);
});

test('timeout cleans only its own attempt and a later explicit retry can succeed', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    try {
        const { loader, scripts, ready } = await fixture();
        const first = loader.loadGoogleMaps(), rejected = assert.rejects(first, { code: 'MAPS_LOAD_FAILED' });
        t.mock.timers.tick(15000); await rejected;
        assert.equal(scripts[0].removed, true); assert.equal(window.clipperdeskMapsReady, undefined);
        const second = loader.loadGoogleMaps(); ready(); await second;
        assert.equal(scripts.length, 2); t.mock.timers.tick(15000); assert.equal(scripts[1].removed, false);
    } finally { t.mock.timers.reset(); }
});

test('a ready callback without the supported API rejects instead of claiming Maps loaded', async () => {
    const { loader, scripts } = await fixture();
    const pending = loader.loadGoogleMaps(), rejected = assert.rejects(pending, { code: 'MAPS_LOAD_FAILED' });
    window.clipperdeskMapsReady(); await rejected;
    assert.equal(scripts[0].removed, true);
});
