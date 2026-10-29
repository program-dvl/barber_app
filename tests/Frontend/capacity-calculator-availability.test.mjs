import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, h, reactive } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
globalThis.capacityCalculatorTest = {
    Button: { inheritAttrs: false, setup(props, { attrs, slots }) { return () => h('button', attrs, slots.default?.()); } },
    Icon: { render() { return h('span'); } },
};
async function load(path) {
    const { descriptor } = parse(await readFile(resolve(path), 'utf8'));
    let code = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replace(/from (['"])vue\1/g, `from '${vue}'`)
        .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/, (_, names) => names.split(',').map(name => `const ${name.trim()}=globalThis.capacityCalculatorTest.Icon;`).join('\n'))
        .replace(/import AppButton from '[^']+';/, 'const AppButton=globalThis.capacityCalculatorTest.Button;')
        .replace(/import AppNotice from '[^']+';/, 'const AppNotice=globalThis.capacityCalculatorTest.Notice;')
        .replace(/import AppSelect from '[^']+';/, 'const AppSelect=globalThis.capacityCalculatorTest.Icon;')
        .replace(/from '@\/Support\/([^']+)';/g, (_, name) => `from '${pathToFileURL(resolve(`resources/js/Support/${name}.js`)).href}';`);
    return (await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
}
globalThis.capacityCalculatorTest.Notice = await load('resources/js/Components/Product/AppNotice.vue');
const Calculator = await load('resources/js/Components/Billing/CapacityCalculator.vue');
const monthly = { ready: false, preview_only: true, base_minor: 2900, location_minor: 1900, staff_minor: 900, currency: 'USD', sms_monthly_allowance: 100,
    staff_tiers: [{ up_to: 4, unit_minor: 900 }, { up_to: 9, unit_minor: 600 }, { up_to: null, unit_minor: 400 }] };
const annual = { ...monthly, base_minor: 29000, location_minor: 19000, staff_minor: 9000, staff_tiers: monthly.staff_tiers.map(tier => ({ ...tier, unit_minor: tier.unit_minor * 10 })) };
const catalog = { available: false, markets: [{ code: 'US', currency: 'USD', intervals: { monthly, annual } }] };
function mount(initial = {}) {
    globalThis.document ||= { activeElement: null };
    globalThis.Document ||= class Document {};
    globalThis.ShadowRoot ||= class ShadowRoot {};
    const root = { children: [] }, reviews = [];
    const renderer = createRenderer({
        createElement: type => ({ kind: type, type, tagName: type.toUpperCase(), props: {}, children: [], listeners: {}, getRootNode: () => globalThis.document, addEventListener(name, fn) { this.listeners[name] = fn; }, removeEventListener() {} }),
        createText: text => ({ text }), createComment: () => ({ text: '' }),
        insert: (node, parent, anchor) => { if (node.parent) node.parent.children = node.parent.children.filter(child => child !== node); node.parent = parent; const at = parent.children.indexOf(anchor); if (at < 0) parent.children.push(node); else parent.children.splice(at, 0, node); },
        remove: node => { node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp: (node, key, old, value) => { node.props[key] = value; node[key] = value; },
    });
    const state = reactive({ catalog, onReview: quote => reviews.push(quote), ...initial });
    const app = renderer.createApp({ render: () => h(Calculator, state) }); app.mount(root);
    const nodes = () => { const result = []; function walk(node) { result.push(node); node.children?.forEach(walk); } walk(root); return result; };
    return { app, state, reviews, nodes, text: () => nodes().map(node => node.text || '').join(' '), buttons: () => nodes().filter(node => node.kind === 'button'), inputs: () => nodes().filter(node => node.kind === 'input') };
}

test('approved estimates remain interactive without enabling unverified checkout', async () => {
    const view = mount();
    try {
        assert.match(view.text(), /Your subscription estimate/); assert.match(view.text(), /\$29\.00/);
        assert.equal(view.buttons().at(-1).props.disabled, true);
        assert.ok(view.inputs().every(input => !input.props.disabled));
        const staff = view.inputs()[1]; staff.value = '20'; staff.listeners.input({ target: staff }); await nextTick();
        assert.match(view.text(), /\$135\.00/); assert.match(view.text(), /2,000 text credits/);
        view.buttons()[1].props.onClick(); await nextTick();
        assert.match(view.text(), /\$1,350\.00/); assert.match(view.text(), /Billed yearly, upfront/);
        assert.equal(view.buttons().at(-1).props.disabled, true); assert.equal(view.reviews.length, 0);
        view.state.catalog = { available: true, markets: [{ code: 'US', currency: 'USD', intervals: { monthly: { ...monthly, ready: true, preview_only: false }, annual: { ...annual, ready: true, preview_only: false } } }] };
        await nextTick(); assert.equal(Number(view.inputs()[1].value), 20); assert.equal(view.buttons().at(-1).props.disabled, false);
        view.buttons().at(-1).props.onClick();
        assert.equal(view.reviews.length, 1); assert.equal(view.reviews[0].total_minor, 135000); assert.equal(view.reviews[0].staff, 20);
    } finally { view.app.unmount(); }
});

test('draft rates require the approved workspace preview marker to be displayed', () => {
    const view = mount({ catalog: { available: false, markets: [{ code: 'US', intervals: { monthly: { ...monthly, preview_only: false } } }] } });
    try { assert.match(view.text(), /Subscriptions are temporarily unavailable/); assert.doesNotMatch(view.text(), /\$29\.00|Review subscription/); }
    finally { view.app.unmount(); }
});

test('provider readiness can disable verified subscription review while estimates stay editable', () => {
    const view = mount({ reviewDisabled: true, catalog: { available: true, markets: [{ code: 'US', intervals: { monthly: { ...monthly, ready: true, preview_only: false } } }] } });
    try { assert.equal(view.buttons().at(-1).props.disabled, true); assert.ok(view.inputs().every(input => !input.props.disabled)); assert.equal(view.reviews.length, 0); }
    finally { view.app.unmount(); }
});
