import { clientMoney, clientDate, clientDay, clientInitials } from './clientWorkspace.js';
export { clientMoney as checkoutMoney, clientInitials as checkoutInitials };
export const checkoutTime = (instant, zone) => `${clientDate(instant,zone)} · ${clientDay(instant,zone)}`;
export const paymentLabel = method => ({cash:'Cash',card:'Card · external terminal',upi:'UPI',bank_transfer:'Bank transfer',payment_link:'Payment link',custom:'Other'})[method] || method;
export const currencyDigits = currency => new Intl.NumberFormat('en',{style:'currency',currency}).resolvedOptions().maximumFractionDigits;
export function parseMoney(value,currency='INR') {
    const text = String(value ?? '').trim(); const digits = currencyDigits(currency);
    if (!new RegExp(`^\\d+(?:\\.\\d{1,${Math.max(1,digits)}})?$`).test(text) || (digits===0 && text.includes('.'))) return null;
    const [whole,fraction=''] = text.split('.');
    const minor = Number(whole)*10**digits+Number(fraction.padEnd(digits,'0'));
    return Number.isSafeInteger(minor) && minor <= 1000000000 ? minor : null;
}
export const moneyInput = (minor,currency) => (Number(minor || 0)/10**currencyDigits(currency)).toFixed(currencyDigits(currency));
export function tenderSummary(payments,balance) {
    const total = payments.reduce((sum,p)=>sum+(Number.isSafeInteger(p.amount_minor) ? p.amount_minor : 0),0);
    return {total,remaining:balance-total,valid:payments.length>0 && payments.length<=8 && payments.every(p=>Number.isSafeInteger(p.amount_minor)&&p.amount_minor>0)&&total<=balance};
}
export function basketPayload(items,tips,reason,applyDeposit) {
    return {items:items.map(item=>({booked_line_id:item.booked_line_id ?? null,service_public_id:item.service_public_id ?? null,product_public_id:item.product_public_id ?? null,quantity:item.quantity,staff_profile_id:item.staff_profile_id ?? null,...(item.overridden ? {unit_price_minor:item.unit_price_minor} : {}),discount_minor:item.discount_minor || 0})),tips:tips.map(tip=>({staff_profile_id:tip.staff_profile_id,amount_minor:tip.amount_minor})),reason:reason || null,apply_deposit:applyDeposit};
}
// The exact command survives reload; never regenerate a key for an uncertain response.
export const pendingStorageKey = (user,business,sale) => `clipperdesk:checkout:pending:${user}:${business}:${sale}`;
export function readPending(storage,key) {
    try { const command = JSON.parse(storage.getItem(key)); return command?.idempotency_key && Array.isArray(command.payments) && command.received_confirmed ? command : null; } catch {return null;}
}
