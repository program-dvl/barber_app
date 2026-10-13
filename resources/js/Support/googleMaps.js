let loading;

export function loadGoogleMaps() {
    if (typeof window === 'undefined') return Promise.reject(new Error('Maps are available in your browser.'));
    if (window.google?.maps?.importLibrary) return Promise.resolve(window.google.maps);
    if (loading) return loading;
    const key = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
    if (!key) return Promise.reject(new Error('Address search is unavailable. You can enter the address manually.'));
    // Google invokes this hook for authentication/restriction failures after script load.
    window.gm_authFailure = () => { for (const callback of failureListeners) callback(); };
    loading = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        const fail = () => {
            clearTimeout(timer); script.remove(); delete window.clipperdeskMapsReady; loading = null;
            reject(new Error('Google Maps could not load. Enter the address manually or try again.'));
        };
        const timer = setTimeout(fail, 15000);
        window.clipperdeskMapsReady = () => {
            clearTimeout(timer); delete window.clipperdeskMapsReady;
            resolve(window.google.maps);
        };
        script.src = `https://maps.googleapis.com/maps/api/js?${new URLSearchParams({ key, v: 'quarterly', loading: 'async', callback: 'clipperdeskMapsReady' })}`;
        script.async = true;
        script.nonce = document.querySelector('script[nonce]')?.nonce || '';
        script.onerror = fail;
        document.head.append(script);
    });
    return loading;
}

export const mapId = () => import.meta.env.VITE_GOOGLE_MAPS_MAP_ID || 'DEMO_MAP_ID';

const failureListeners = new Set();
export function onGoogleMapsFailure(callback) {
    failureListeners.add(callback);
    return () => failureListeners.delete(callback);
}
