export const ACQUISITION_PREFERENCES_EVENT = 'clipperdesk:acquisition-preferences';
export function openAcquisitionPreferences() {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event(ACQUISITION_PREFERENCES_EVENT));
    }
}

export const ACQUISITION_CONSENT_KEY = 'clipperdesk:marketplace-acquisition:v1';
export function acquisitionConsent(browser = globalThis.window, now = Date.now()) {
    try {
        if (browser.navigator?.globalPrivacyControl === true || ['1', 'yes'].includes(String(browser.navigator?.doNotTrack ?? browser.doNotTrack ?? '').toLowerCase())) return false;
        const general = JSON.parse(browser.localStorage.getItem('clipperdesk:analytics-consent:v1'));
        if (general?.choice === 'rejected' && general.expires_at > now) return false;
        const value = JSON.parse(browser.localStorage.getItem(ACQUISITION_CONSENT_KEY));
        return value?.version === 'mpg-consent-v1' && value.choice === 'accepted' && value.expires_at > now && value.expires_at <= now + 30 * 86400000;
    } catch { return false; }
}
export function acquisitionLabels({ referrer = '', url = '', kind = '' } = {}) {
    let owner = 'unknown', source = 'direct';
    try {
        const value = new URL(url).searchParams.get('utm_source');
        if (['owner_website', 'whatsapp', 'google_business_profile'].includes(value)) { owner = value; source = 'owner_link'; }
        const host = new URL(referrer).hostname;
        if (owner === 'unknown' && /^(?:(?:www\.)?google\.(?:com|ca|co\.uk|co\.in)|(?:www\.)?bing\.com|duckduckgo\.com)$/.test(host)) source = 'external_search';
    } catch { /* Unknown source remains unknown; never retain raw input. */ }
    if (source === 'direct' && kind !== 'profile') source = 'marketplace_discovery';
    return { source, owner_source: owner };
}
