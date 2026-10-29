let loading;
let activeLoad;
let authenticationFailed = false;
let authenticationHookInstalled = false;
const failureListeners = new Set();

function loadError(code) {
    const error = new Error('Address search is unavailable right now. You can enter the address manually.');
    error.code = code;
    return error;
}

function installAuthenticationHook() {
    if (authenticationHookInstalled) return;
    const previous = window.gm_authFailure;
    window.gm_authFailure = () => {
        authenticationFailed = true;
        activeLoad?.fail('MAPS_AUTHENTICATION_FAILED');
        for (const callback of failureListeners) {
            try { callback('MAPS_AUTHENTICATION_FAILED'); } catch { /* One consumer must not block the others. */ }
        }
        if (typeof previous === 'function') {
            try { previous(); } catch { /* Preserve the existing hook without exposing provider details. */ }
        }
    };
    authenticationHookInstalled = true;
}

export function loadGoogleMaps() {
    if (typeof window === 'undefined') return Promise.reject(loadError('MAPS_LOAD_FAILED'));
    if (authenticationFailed) return Promise.reject(loadError('MAPS_AUTHENTICATION_FAILED'));
    installAuthenticationHook();
    if (typeof window.google?.maps?.importLibrary === 'function') return Promise.resolve(window.google.maps);
    if (loading) return loading;
    const key = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
    if (!key) return Promise.reject(loadError('MAPS_CONFIGURATION_MISSING'));
    let resolveLoad, rejectLoad;
    loading = new Promise((resolve, reject) => { resolveLoad = resolve; rejectLoad = reject; });
    const pending = loading;
    const script = document.createElement('script');
    const request = { script, settled: false, timer: null };
    activeLoad = request;
    const finish = () => {
        request.settled = true;
        clearTimeout(request.timer);
        if (window.clipperdeskMapsReady === request.ready) delete window.clipperdeskMapsReady;
        script.onerror = null;
        activeLoad = null;
        loading = null;
    };
    request.fail = code => {
        if (request.settled || activeLoad !== request) return;
        finish();
        script.remove();
        rejectLoad(loadError(code));
    };
    request.ready = () => {
        if (request.settled || activeLoad !== request) return;
        if (typeof window.google?.maps?.importLibrary !== 'function') {
            request.fail('MAPS_LOAD_FAILED');
            return;
        }
        finish();
        resolveLoad(window.google.maps);
    };
    request.timer = setTimeout(() => request.fail('MAPS_LOAD_FAILED'), 15000);
    window.clipperdeskMapsReady = request.ready;
    script.src = `https://maps.googleapis.com/maps/api/js?${new URLSearchParams({ key, v: 'quarterly', loading: 'async', callback: 'clipperdeskMapsReady' })}`;
    script.async = true;
    script.nonce = document.querySelector('script[nonce]')?.nonce || '';
    script.onerror = () => request.fail('MAPS_LOAD_FAILED');
    try { document.head.append(script); } catch { request.fail('MAPS_LOAD_FAILED'); }
    return pending;
}

export const mapId = () => import.meta.env.VITE_GOOGLE_MAPS_MAP_ID || 'DEMO_MAP_ID';

export function onGoogleMapsFailure(callback) {
    failureListeners.add(callback);
    if (authenticationFailed) {
        try { callback('MAPS_AUTHENTICATION_FAILED'); } catch { /* Match notification isolation after initial load. */ }
    }
    return () => failureListeners.delete(callback);
}
