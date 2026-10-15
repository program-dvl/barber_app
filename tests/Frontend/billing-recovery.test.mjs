import test from 'node:test';
import assert from 'node:assert/strict';
import { billingRecoveryFailure } from '../../resources/js/Support/billingRecovery.js';

const at = Date.parse('2026-10-15T10:00:00Z');
test('uses Retry-After seconds so the customer can wait before retrying', () => {
    const value = billingRecoveryFailure({ response: { status: 429, headers: { 'retry-after': '37' }, data: { code: 'billing_retry_later', message: 'Your selection is saved.' } } }, at);
    assert.equal(value.retryAt, at + 37000);
    assert.equal(value.message, 'Your selection is saved.');
    assert.equal(value.title, 'Let’s give checkout a moment');
});
test('handles HTTP date Retry-After and reasonable defaults without NaN', () => {
    assert.equal(billingRecoveryFailure({ response: { status: 429, headers: { 'retry-after': 'Thu, 15 Oct 2026 10:00:20 GMT' } } }, at).retryAt, at + 20000);
    assert.equal(billingRecoveryFailure({ response: { status: 429 } }, at).retryAt, at + 60000);
    assert.equal(billingRecoveryFailure({}, at).retryAt, at + 15000);
});
test('hides raw provider diagnostics and exposes only valid support references', () => {
    const bad = billingRecoveryFailure({ response: { status: 500, data: { message: 'sk_live_private diagnostic', reference: '<script>', support_url: 'javascript:alert(1)' } } }, at);
    assert.doesNotMatch(bad.message, /private|sk_live/);
    assert.equal(bad.reference, null);
    assert.equal(bad.supportUrl, null);
    const good = billingRecoveryFailure({ response: { status: 409, data: { code: 'billing_review_required', support_required: true, reference: '01M4W14ZTFHSDV28XGATK1TSCG', support_url: 'mailto:help@example.test?subject=Checkout' } } }, at);
    assert.equal(good.supportRequired, true);
    assert.match(good.supportUrl, /^mailto:/);
    assert.equal(good.reference, '01M4W14ZTFHSDV28XGATK1TSCG');
});
test('field validation remains immediately correctable without a retry countdown', () => {
    const value = billingRecoveryFailure({ response: { status: 422, data: { errors: { staff: ['Enter at least one staff member.'] } } } }, at);
    assert.equal(value.retryAt, at);
    assert.equal(value.message, 'Enter at least one staff member.');
});

test('consumes a Stripe return marker once for the correct business', async () => {
    const { markBillingDeparture, consumeBillingDeparture } = await import('../../resources/js/Support/billingRecovery.js');
    const values = new Map();
    const storage = { setItem:(key,value)=>values.set(key,value), getItem:key=>values.get(key), removeItem:key=>values.delete(key) };
    markBillingDeparture('business-a', storage);
    assert.equal(consumeBillingDeparture('business-b', storage), false);
    assert.equal(consumeBillingDeparture('business-a', storage), true);
    assert.equal(consumeBillingDeparture('business-a', storage), false);
    assert.equal(values.size, 0);
});
test('blocked session storage never prevents checkout navigation', async () => {
    const { markBillingDeparture, consumeBillingDeparture } = await import('../../resources/js/Support/billingRecovery.js');
    const blocked = { setItem(){throw Error('blocked');}, getItem(){throw Error('blocked');} };
    assert.doesNotThrow(()=>markBillingDeparture('a', blocked));
    assert.equal(consumeBillingDeparture('a', blocked), false);
});
