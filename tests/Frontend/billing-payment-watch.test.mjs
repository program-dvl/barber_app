import test from 'node:test';
import assert from 'node:assert/strict';
import { watchBillingPayment } from '../../resources/js/Support/billingPaymentWatch.js';

const processing = { status: 'submitted', kind: 'checkout', checkout_status: 'processing', checkout_url: null };
function harness(check) {
    const jobs = new Map(), errors = [], stops = [], delays = [];
    let id = 0;
    const watcher = watchBillingPayment({ check, onError: error => errors.push(error), onStop: reason => stops.push(reason),
        schedule: (callback, delay) => { delays.push(delay); jobs.set(++id, callback); return id; }, cancel: key => jobs.delete(key) });
    return { watcher, jobs, errors, stops, delays, async tick() { const [key, run] = jobs.entries().next().value; jobs.delete(key); await run(); } };
}
test('automatically follows processing to paid once, with no overlapping requests', async () => {
    let calls = 0, resolve;
    const flow = harness(() => ++calls === 1 ? Promise.resolve(processing) : new Promise(done => { resolve = done; }));
    await flow.watcher.start();
    assert.deepEqual(flow.delays, [2000]);
    const pending = flow.tick();
    assert.equal(calls, 2); assert.equal(flow.jobs.size, 0);
    resolve({ ...processing, status: 'applied', checkout_status: 'confirmed' }); await pending;
    assert.equal(flow.jobs.size, 0); assert.deepEqual(flow.stops, ['settled']);
});
test('stops after six successful processing reads over thirty seconds', async () => {
    let calls = 0;
    const flow = harness(async () => { calls++; return processing; });
    await flow.watcher.start();
    while (flow.jobs.size) await flow.tick();
    assert.equal(calls, 6); assert.equal(flow.delays.reduce((a, b) => a + b), 30000);
    assert.deepEqual(flow.stops, ['pending']);
});
for (const result of [{ ...processing, checkout_status: 'pending', checkout_url: 'https://checkout.stripe.com/open' },
    { ...processing, checkout_url: 'https://invoice.stripe.com/payment' }, { ...processing, checkout_status: null }, { ...processing, status: 'expired' }]) {
    test(`does not poll an open, action-required, unknown or closed checkout: ${JSON.stringify(result)}`, async () => {
        const flow = harness(async () => result); await flow.watcher.start(); assert.equal(flow.jobs.size, 0);
    });
}
for (const status of [409, 429, 503]) {
    test(`stops immediately after ${status}, leaving Retry-After to the recovery notice`, async () => {
        const error = { response: { status, headers: { 'retry-after': '37' } } };
        const flow = harness(async () => { throw error; }); await flow.watcher.start();
        assert.equal(flow.jobs.size, 0); assert.deepEqual(flow.errors, [error]); assert.deepEqual(flow.stops, ['error']);
    });
}
test('navigation cancels delayed checks and ignores a late response or failure', async () => {
    for (const fails of [false, true]) {
        let resolve, reject;
        const flow = harness(() => new Promise((done, fail) => { resolve = done; reject = fail; }));
        const pending = flow.watcher.start(); flow.watcher.stop();
        if (fails) reject(Error('late')); else resolve(processing);
        await pending; assert.equal(flow.jobs.size, 0); assert.deepEqual(flow.errors, []); assert.deepEqual(flow.stops, ['stopped']);
    }
    const flow = harness(async () => processing); await flow.watcher.start(); flow.watcher.stop(); assert.equal(flow.jobs.size, 0);
});
