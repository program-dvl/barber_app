// Follow only a payment that is already processing. An open Checkout needs the
// customer, while errors and unknown preparations must never trigger a retry loop.
export function watchBillingPayment({ check, onError, onStop, schedule = setTimeout, cancel = clearTimeout }) {
    const delays = [2000, 3000, 5000, 8000, 12000];
    let version = 0, timer = null;
    function stop(notify = true) {
        version++;
        if (timer !== null) cancel(timer);
        timer = null;
        if (notify) onStop('stopped');
    }
    async function start(initialResult = null) {
        stop(false);
        const current = version;
        let checks = initialResult ? 1 : 0;
        function follow(result) {
            if (current !== version) return;
            if (result?.status !== 'submitted' || result.kind !== 'checkout'
                || result.checkout_status !== 'processing' || result.checkout_url) {
                onStop('settled');
                return;
            }
            if (checks > delays.length) { onStop('pending'); return; }
            timer = schedule(run, delays[checks - 1]);
        }
        async function run() {
            timer = null;
            if (current !== version) return;
            checks++;
            try { const result = await check(); follow(result); }
            catch (error) {
                if (current !== version) return;
                onError(error);
                onStop('error');
            }
        }
        if (initialResult) follow(initialResult);
        else await run();
    }
    return { start, stop };
}
