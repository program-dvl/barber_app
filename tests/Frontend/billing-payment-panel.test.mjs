import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
async function component(path, panel = false) {
    const { descriptor } = parse(await readFile(resolve(path), 'utf8'));
    let code = compileScript(descriptor, { id: 'billing-payment-test', inlineTemplate: true }).content
        .replace(/from (['"])vue\1/g, `from '${vue}'`);
    code = code.replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/, (_, names) => names.split(',').map(name => `const ${name.trim()}={render(){return testH('span')}};`).join('\n'));
    if (panel) {
        code = code.replace(/import \{ router \} from '@inertiajs\/vue3';/, 'const router = {reload: (...args)=>globalThis.billingPanelTest.reload(...args)};')
            .replace("import axios from 'axios';", 'const axios={post:(...args)=>globalThis.billingPanelTest.post(...args),isCancel:()=>false};')
            .replace(/import AppNotice from '[^']+';/, 'const AppNotice = globalThis.billingPanelTest.Notice;')
            .replace(/import AppButton from '[^']+';/, "const AppButton={inheritAttrs:false,setup(props,{attrs,slots}){return ()=>testH('button',attrs,slots.default?.())}};")
            .replace(/import (CapacityCalculator|AppDialog) from '[^']+';/g, 'const $1={render(){return null}};')
            .replace(/from '@\/Support\/([^']+)';/g, (_, name) => `from '${pathToFileURL(resolve(`resources/js/Support/${name}.js`)).href}';`);
    }
    return (await import(`data:text/javascript;base64,${Buffer.from(`import { h as testH } from '${vue}';\n${code}`).toString('base64')}`)).default;
}
const Notice = await component('resources/js/Components/Product/AppNotice.vue');
globalThis.billingPanelTest = { Notice };
const Panel = await component('resources/js/Components/Billing/CapacityBillingPanel.vue', true);
const quote = { locations: 5, staff: 10, total_minor: 17100, currency: 'USD', billing_interval: 'monthly' };
const request = { change_id: 'change', status: 'submitted', kind: 'checkout', quote };
const processing = { ...request, checkout_status: 'processing', checkout_url: null };
const flush = async () => { for (let i = 0; i < 16; i++) await nextTick(); };
function mount(post, props = {}) {
    const root = { children: [] }, calls = [], reloads = [];
    globalThis.billingPanelTest = { Notice, post: (...args) => { calls.push(args); return post(...args); }, reload: options => reloads.push(options) };
    globalThis.window = { setInterval: () => 1, clearInterval() {}, location: { assign() { throw Error('Automatic checks must not navigate to checkout'); } } };
    globalThis.route = name => name;
    const renderer = createRenderer({
        createElement: type => ({ type, props: {}, children: [] }), createText: text => ({ text }), createComment: () => ({ text: '' }),
        insert: (child, parent, anchor) => { if (child.parent) child.parent.children = child.parent.children.filter(node => node !== child); child.parent = parent; const i = parent.children.indexOf(anchor); if (i < 0) parent.children.push(child); else parent.children.splice(i, 0, child); },
        remove: node => { node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp: (node, key, previous, next) => node.props[key] = next,
    });
    const app = renderer.createApp(Panel, { request, ready: true, businessId: 'business', timeZone: 'UTC', ...props }); app.mount(root);
    const nodes = () => { const all = []; function walk(node) { all.push(node); node.children?.forEach(walk); } walk(root); return all; };
    return { app, calls, reloads, nodes, text: () => nodes().map(node => node.text || '').join(' '), buttons: () => nodes().filter(node => node.type === 'button') };
}
for (const interval of ['monthly', 'annual']) {
    test(`actual billing panel confirms ${interval} payment automatically without a click`, async t => {
        t.mock.timers.enable({ apis: ['setTimeout'] });
        let resolve, calls = 0;
        const fixture = mount(() => ++calls === 1 ? new Promise(done => { resolve = done; }) : Promise.resolve({ data: { ...processing, status: 'applied', checkout_status: 'confirmed' } }),
            { request: { ...request, quote: { ...quote, billing_interval: interval } } });
        try {
            assert.match(fixture.text(), /Checking your payment/); assert.doesNotMatch(fixture.text(), /unfinished checkout/);
            resolve({ data: processing }); await flush();
            assert.equal(fixture.calls[0][1].verify_only, true); assert.equal(fixture.buttons().length, 0);
            t.mock.timers.tick(2000); await flush();
            assert.match(fixture.text(), /Your subscription is active/); assert.doesNotMatch(fixture.text(), /unfinished checkout|Checking your payment/);
            assert.equal(fixture.calls.length, 2); assert.equal(fixture.reloads.length, 1);
            for (const field of ['tenant', 'subscription', 'entitlements', 'usage', 'invoices', 'attention']) assert.ok(fixture.reloads[0].only.includes(field));
            assert.ok(fixture.nodes().some(node => node.props?.role === 'status'));
        } finally { fixture.app.unmount(); t.mock.timers.reset(); }
    });
}
test('actual panel immediately shows success when the webhook already applied payment', async () => {
    const fixture = mount(() => { throw Error('No provider request needed'); }, { request: null, checkoutReturn: { status: 'confirmed', request: { ...request, status: 'applied' } } });
    try { await flush(); assert.match(fixture.text(), /Your subscription is active/); assert.equal(fixture.calls.length, 0); }
    finally { fixture.app.unmount(); }
});
test('actual panel offers continuation after an unpaid return without pretending payment succeeded', async () => {
    const fixture = mount(async () => ({ data: { ...request, checkout_status: 'pending', checkout_url: 'https://checkout.stripe.com/open' } }));
    try {
        await flush(); assert.match(fixture.text(), /unfinished checkout/); assert.doesNotMatch(fixture.text(), /subscription is active/);
        assert.equal(fixture.calls.length, 1); assert.match(fixture.text(), /Continue secure checkout/); assert.match(fixture.text(), /Start again/);
        assert.ok(fixture.buttons().every(button => !button.props.disabled));
    } finally { fixture.app.unmount(); }
});
test('actual panel stops on throttling, displays the wait, and retains its purchase summary', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const fixture = mount(async () => { throw { response: { status: 429, headers: { 'retry-after': '37' } } }; });
    try {
        await flush(); assert.match(fixture.text(), /give checkout a moment/); assert.match(fixture.text(), /37 seconds/);
        assert.match(fixture.text(), /5 locations/); assert.match(fixture.text(), /10 bookable staff/);
        assert.ok(fixture.buttons().every(button => button.props.disabled));
        t.mock.timers.tick(60000); await flush(); assert.equal(fixture.calls.length, 1);
        assert.ok(fixture.nodes().some(node => node.props?.role === 'alert'));
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});
test('actual panel ignores a payment response after leaving the page', async () => {
    let resolve;
    const fixture = mount(() => new Promise(done => { resolve = done; })); fixture.app.unmount();
    resolve({ data: { ...processing, status: 'applied' } }); await flush(); assert.equal(fixture.reloads.length, 0);
});
