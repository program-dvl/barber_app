export const calculatorCurrencies = ['USD', 'CAD', 'GBP', 'INR', 'AUD', 'EUR'];
export const exampleNoShows = Object.freeze({ value: '60', missed: '8', recovery: 50 });

function amountMinor(value, limit = 100_000_000) {
    const text = String(value ?? '').trim();
    if (!/^(?:\d+(?:\.\d{1,2})?|\.\d{1,2})$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(minor) && minor <= limit ? minor : null;
}
export const appointmentValueMinor = value => amountMinor(value);
export const collectedAmountMinor = value => amountMinor(value, 10_000_000_000_000);

export function missedAppointmentCount(value) {
    const text = String(value ?? '').trim();
    if (!/^\d+$/.test(text)) return null;
    const count = Number(text);
    return Number.isSafeInteger(count) && count <= 100_000 ? count : null;
}

export function noShowEstimate(value, missed, recovery = 50, adjustments = {}) {
    const valueMinor = appointmentValueMinor(value), count = missedAppointmentCount(missed);
    const refilled = missedAppointmentCount(adjustments.refilled ?? '0');
    const collectedMinor = collectedAmountMinor(adjustments.collected ?? '0');
    const avoidedPerVisitMinor = appointmentValueMinor(adjustments.avoided ?? '0');
    if ([valueMinor,count,refilled,collectedMinor,avoidedPerVisitMinor].some(n => n === null) || refilled > count || !Number.isInteger(recovery) || recovery < 0 || recovery > 100) return null;
    const grossMonthlyMinor = valueMinor * count;
    const refilledValueMinor = valueMinor * refilled;
    const emptySlots = count - refilled;
    const unfilledValueMinor = valueMinor * emptySlots;
    const unpaidMonthlyMinor = Math.max(0, unfilledValueMinor - collectedMinor);
    const avoidedMonthlyMinor = avoidedPerVisitMinor * emptySlots;
    const monthlyMinor = Math.max(0, unpaidMonthlyMinor - avoidedMonthlyMinor);
    return {
        grossMonthlyMinor, refilledValueMinor, emptySlots, unfilledValueMinor, collectedMinor,
        unpaidMonthlyMinor, avoidedMonthlyMinor, monthlyMinor, annualMinor: monthlyMinor * 12,
        // A proportional reduction in empty slots, with the same average retained fees/costs.
        recoveryMonthlyMinor: Math.round(monthlyMinor * recovery / 100),
        recoveryAnnualMinor: Math.round(monthlyMinor * recovery / 100) * 12,
        recoveryAppointments: emptySlots * recovery / 100,
        covered: unfilledValueMinor > 0 && collectedMinor + avoidedMonthlyMinor >= unfilledValueMinor,
    };
}

export function noShowMoney(minor, currency = 'USD') {
    if (!calculatorCurrencies.includes(currency) || !Number.isSafeInteger(minor) || minor < 0) return '—';
    return new Intl.NumberFormat('en', { style: 'currency', currency, currencyDisplay: 'narrowSymbol', maximumFractionDigits: minor % 100 ? 2 : 0 }).format(minor / 100);
}
