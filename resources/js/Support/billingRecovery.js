// Customer recovery state shared by billing actions; never display provider diagnostics.
export function billingRecoveryFailure(exception, now = Date.now()) {
    const data = exception.response?.data || {};
    const status = exception.response?.status;
    const retry = exception.response?.headers?.['retry-after'] ?? data.retry_after;
    const numeric = Number(retry);
    const seconds = retry !== undefined && Number.isFinite(numeric) ? numeric
        : retry && Number.isFinite(Date.parse(retry)) ? (Date.parse(retry) - now) / 1000 : status === 429 ? 60 : 15;
    const validation = Object.values(data.errors || {}).flat()[0];
    const safeMessage = data.code?.startsWith('billing_') && data.message;
    return {
        title: status === 429 ? 'Let’s give checkout a moment' : data.support_required ? 'We need to help with this checkout' : 'Checkout is temporarily unavailable',
        message: validation || safeMessage || 'We couldn’t complete this step. Your selection is saved. Please try again in a moment.',
        supportRequired: Boolean(data.support_required),
        supportUrl: typeof data.support_url === 'string' && /^mailto:[^?\s@]+@[^?\s@]+\?/.test(data.support_url) ? data.support_url : null,
        reference: typeof data.reference === 'string' && /^[0-9A-HJKMNP-TV-Z]{26}$/i.test(data.reference) ? data.reference : null,
        retryAt: validation ? now : now + Math.min(86400, Math.max(1, Math.ceil(seconds))) * 1000,
    };
}

// A full Stripe departure can restore stale Inertia history props on Back.
// Store only a transient return marker, never quantities or payment details.
export function markBillingDeparture(businessId, storage) {
    try { (storage ?? globalThis.sessionStorage)?.setItem(`clipperdesk.billing-return.${businessId}`, '1'); } catch { /* Storage may be disabled. */ }
}
export function consumeBillingDeparture(businessId, storage) {
    try {
        const target = storage ?? globalThis.sessionStorage;
        const key = `clipperdesk.billing-return.${businessId}`;
        const marked = target?.getItem(key) === '1';
        if (marked) target.removeItem(key);
        return marked;
    } catch { return false; }
}
