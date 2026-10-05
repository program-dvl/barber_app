const zeroDecimal = new Set(['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']);
export const billingLabels = { 'locations.max': 'Locations', 'staff.max': 'Active team members', 'messaging.monthly_allowance': 'SMS allowance', 'messaging.branded_sender': 'Branded SMS sender', 'messaging.two_way': 'Two-way messaging', 'deposits.enabled': 'Appointment deposits', 'inventory.enabled': 'Inventory', 'reporting.advanced': 'Advanced reports', 'branding.custom': 'Custom branding', 'support.priority': 'Priority support', 'exports.enabled': 'Data export' };
export function billingMoney(minor, currency = 'USD', locale = 'en-US') {
    if (minor === null || minor === undefined || !Number.isFinite(Number(minor))) return 'Not available';
    currency = currency.toUpperCase();
    const value = Number(minor) / (zeroDecimal.has(currency) ? 1 : 100);
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(value);
}
export function billingDate(value, timeZone = 'UTC', locale = 'en-US') {
    if (!value) return 'Not scheduled';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 'Not available' : new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric', timeZone }).format(date);
}
export function yearlySavings(plan, yearly) {
    const monthly = plan.prices?.find(price => price.billing_interval === 'monthly' && price.currency === yearly?.currency);
    return monthly && yearly?.billing_interval === 'annual' ? Math.max(0, monthly.amount_minor * 12 - yearly.amount_minor) : 0;
}
export function invoiceStatus(invoice) {
    if (invoice.status === 'open' && invoice.last_payment_failed_at) return 'Payment failed';
    return { paid: 'Paid', open: 'Open', draft: 'Draft', uncollectible: 'Uncollectible', void: 'Voided' }[invoice.status] || 'Needs review';
}
export function subscriptionStatus(subscription) {
    return { trialing: 'Free trial', active: 'Active', past_due: 'Payment due', grace: 'Payment due', restricted: 'Read-only access', cancel_scheduled: 'Cancellation scheduled', canceled: 'Cancelled', terminated: 'Subscription ended' }[subscription.status] || 'Needs review';
}
export function planAction(subscription, plan, price, ranks, pending = false) {
    if (!price) return 'unavailable';
    if (subscription.billing_plan_id === plan.id && subscription.billing_interval === price.billing_interval && subscription.has_provider_subscription && !['terminated', 'canceled'].includes(subscription.status)) return 'current';
    if (!subscription.has_provider_subscription || ['terminated', 'canceled'].includes(subscription.status)) return 'subscribe';
    if ((ranks[plan.code] ?? 0) < (ranks[subscription.plan.code] ?? 0)) return 'support';
    if (pending || subscription.status !== 'active') return 'unavailable';
    return subscription.billing_plan_id === plan.id ? 'interval_switch' : 'upgrade';
}
export function limitState(used, limit) {
    if (!Number.isFinite(limit)) return 'none';
    return used > limit ? 'over' : used === limit ? 'full' : (limit > 0 && used / limit >= .9) ? 'near' : 'normal';
}
export function differingFeatures(plans) {
    const values = plans.map(plan => Object.fromEntries((plan.entitlements || []).map(item => [item.definition.key, item.value])));
    return Object.keys(billingLabels).filter(key => values.length > 1 && new Set(values.map(value => JSON.stringify(value[key] ?? null))).size > 1);
}

export function cardExpiryState(month, year, at = Date.now()) {
    if (!month || !year) return null;
    const ends = Date.UTC(year, month, 1);
    return ends <= at ? 'expired' : ends - at <= 30 * 86400000 ? 'soon' : null;
}
