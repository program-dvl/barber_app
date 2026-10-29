import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, h } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const empty = { render() { return null; } };
const container = { setup(props, { slots }) { return () => h('div', slots.default?.()); } };
const Button = { inheritAttrs: false, setup(props, { attrs, slots }) { return () => h('button', attrs, slots.default?.()); } };
const Dialog = { setup(props, { slots, expose }) { expose({ open() {}, close() {} }); return () => h('div', slots.default?.()); } };
const Phone = { props: ['modelValue'], emits: ['update:modelValue'], setup(props, { attrs, emit }) { return () => h('input', { ...attrs, value: props.modelValue, onInput: event => emit('update:modelValue', event.target.value) }); } };
globalThis.bookingPresentationTest = { components: { AppButton: Button, AppDialog: Dialog, FormField: container, PublicBookingLayout: container, PhoneInput: Phone } };
async function load(path, welcome = false) {
    const { descriptor } = parse(await readFile(resolve(path), 'utf8'));
    let source = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replace(/from (['"])vue\1/g, `from '${vue}'`)
        .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/g, (_, names) => names.split(',').map(name => `const ${name.trim()} = {render(){return null}};`).join('\n'))
        .replace("import axios from 'axios';", 'const axios = { post: (...args) => globalThis.bookingPresentationTest.post(...args) };')
        .replace(/from '@\/Support\/([^']+)';/g, (_, name) => `from '${pathToFileURL(resolve(`resources/js/Support/${name}.js`)).href}';`)
        .replace(/import (\w+) from '@\/[^']+';/g, (_, name) => `const ${name} = globalThis.bookingPresentationTest.components.${name} || {render(){return null}};`);
    return (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
}
globalThis.bookingPresentationTest.components.AppNotice = await load('resources/js/Components/Product/AppNotice.vue');
const Professionals = await load('resources/js/Components/Booking/ProfessionalCards.vue');
globalThis.bookingPresentationTest.components.ProfessionalCards = Professionals;
const Welcome = await load('resources/js/Pages/Booking/Welcome.vue', true);
const flush = async () => { for (let index = 0; index < 16; index++) await nextTick(); };
const date = '2035-10-09';
const slot = { starts_at_utc: `${date}T10:00:00Z`, ends_at_utc: `${date}T10:30:00Z`, price_minor: 3000, currency_code: 'USD' };
const policy = { services: [{ name: 'Cut', price_minor: 3000, currency_code: 'USD', price_type: 'fixed', visit_minutes: 30 }], deposit_status: 'not_required', cancellation_policy: 'Cancel at least one day before.', terms_url: '/terms', privacy_url: '/privacy', sms_wording: 'Send appointment texts.', marketing_wording: 'Send news.' };
function fixtureCatalog(staffPreference = 'any_or_preferred') {
    return {
        locations: ['main', 'other'].map(public_id => ({ public_id, name: `${public_id} salon`, today: date, time_zone: 'UTC' })),
        services: [{ public_id: 'cut', name: 'Cut', kind: 'service', category: 'Hair', price_minor: 3000, currency_code: 'USD', price_type: 'fixed', active_minutes: 30, visit_minutes: 30, duration_minutes: 30, processing_minutes: 0, cleanup_minutes: 0, maximum_advance_days: 30, location_ids: ['main', 'other'], addon_ids: [] }],
        staff: [{ public_id: 'avery', display_name: 'Avery Brooks', title: 'Senior stylist', biography: 'Ten years of thoughtful hair care.', photo_url: null, location_ids: ['main'], service_ids: ['cut'] },
            { public_id: 'other-professional', display_name: 'Other Branch', photo_url: null, location_ids: ['other'], service_ids: ['cut'] },
            { public_id: 'unqualified', display_name: 'Different Specialty', photo_url: null, location_ids: ['main'], service_ids: ['color'] }],
        policy: { online_staff_preference: staffPreference, online_new_client_rule: 'allow' },
    };
}
function mount({ staffPreference, preview = false, saved = null, post } = {}) {
    const root = { children: [] }, calls = [], storage = [];
    globalThis.sessionStorage = { getItem: key => { storage.push(['get', key]); return saved ? JSON.stringify(saved) : null; }, setItem: (key, value) => storage.push(['set', key, JSON.parse(value)]), removeItem: key => storage.push(['remove', key]) };
    globalThis.document = { querySelector: () => null, activeElement: null };
    globalThis.window = { scrollTo() {} };
    globalThis.bookingPresentationTest.post = async (url, body) => {
        calls.push({ url, body: body ? JSON.parse(JSON.stringify(body)) : body });
        if (post) { const custom = await post(url, body); if (custom !== undefined) return custom; }
        if (url === 'public.booking.start') return { data: { flow: 'flow-one', secret: 'secret-one', expires_at: new Date(Date.now() + 7200000).toISOString() } };
        if (url === 'public.booking.resume') return { data: { status: 'started', expires_at: saved?.expires_at } };
        if (url === 'public.booking.search') return { data: { slots: [slot] } };
        if (url === 'public.booking.hold') return { data: { state: { starts_at: slot.starts_at_utc }, policy, hold_expires_at: new Date(Date.now() + 600000).toISOString() } };
        if (url === 'public.booking.details') return { data: { policy } };
        if (url === 'public.booking.release') return { data: { expires_at: new Date(Date.now() + 7200000).toISOString() } };
        throw Error(`Unexpected booking request: ${url}`);
    };
    const renderer = createRenderer({
        createElement(type) { return { type, tagName: type.toUpperCase(), props: {}, children: [], listeners: {}, value: '', addEventListener(name, callback) { this.listeners[name] = callback; }, removeEventListener() {}, setAttribute(name, value) { this.props[name] = value; }, removeAttribute(name) { delete this.props[name]; } }; },
        createText: text => ({ text }), createComment: () => ({ text: '' }),
        insert(child, parent, anchor) { if (child.parent) child.parent.children = child.parent.children.filter(node => node !== child); child.parent = parent; const index = parent.children.indexOf(anchor); if (index < 0) parent.children.push(child); else parent.children.splice(index, 0, child); },
        remove(node) { node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp(node, key, before, after) { node.props[key] = after; if (key === 'value') node.value = after; if (key === 'type') node.type = after; if (key === 'checked') node.checked = after; },
    });
    const catalog = fixtureCatalog(staffPreference);
    const app = renderer.createApp({ render: () => h(Welcome, { business: { name: 'Test Studio', booking_slug: 'test-studio', currency_code: 'USD', phone: '+15555550100', reviews: { count: 0, items: [] } }, catalog, preview }) });
    app.provide('route', name => name); app.mount(root);
    const nodes = () => { const all = []; function walk(node) { all.push(node); node.children?.forEach(walk); } walk(root); return all; };
    const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ').replace(/\s+/g, ' ');
    const button = label => nodes().find(node => node.tagName === 'BUTTON' && text(node).trim() === label);
    const click = async (node, force = false) => { assert.ok(node, 'Expected control is rendered'); if (!force) assert.ok(!node.props.disabled, 'Expected control is enabled'); const handlers = Array.isArray(node.props.onClick) ? node.props.onClick : [node.props.onClick]; for (const handler of handlers) await handler?.({ preventDefault() {}, stopPropagation() {} }); await flush(); };
    const input = async (id, value) => { const node = nodes().find(node => node.tagName === 'INPUT' && node.props?.id === id); assert.ok(node, `Expected ${id}`); node.value = value; await (node.listeners.input || node.props.onInput)?.({ target: node }); await flush(); };
    const submit = async () => { const node = nodes().find(node => node.tagName === 'FORM'); assert.ok(node); await node.props.onSubmit({ preventDefault() {}, stopPropagation() {} }); await flush(); };
    return { app, calls, storage, nodes, text: () => text(root), nodeText: text, button, click, input, submit, catalog };
}
function clock(t) { t.mock.timers.enable({ apis: ['Date', 'setTimeout', 'setInterval'], now: Date.parse(`${date}T09:00:00Z`) }); }
const searches = fixture => fixture.calls.filter(call => call.url === 'public.booking.search');
async function selectCut(fixture) { await fixture.click(fixture.nodes().find(node => node.tagName === 'BUTTON' && node.props.class === 'booking-service')); }
async function times(fixture) { await selectCut(fixture); await fixture.click(fixture.button('Choose a time')); }

test('actual booking uses first-available policy without exposing professional selection and falls back to initials', async t => {
    clock(t); const fixture = mount({ staffPreference: 'any_only' });
    try {
        assert.match(fixture.text(), /AB/); assert.equal(fixture.nodes().filter(node => node.tagName === 'IMG').length, 0);
        assert.ok(!fixture.nodes().some(node => node.props?.['aria-label'] === 'Choose your professional'));
        await times(fixture); assert.equal(searches(fixture).length, 1); assert.equal(searches(fixture)[0].body.staff, null);
        assert.ok(!fixture.nodes().some(node => node.props?.['aria-label'] === 'Choose your professional'));
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});

test('required professional waits for a choice and offers only qualified staff at the selected branch', async t => {
    clock(t); const fixture = mount({ staffPreference: 'preferred_required' });
    try {
        await times(fixture); assert.equal(searches(fixture).length, 0); assert.match(fixture.text(), /Choose a professional to see times/);
        assert.doesNotMatch(fixture.text(), /Other Branch|Different Specialty/);
        assert.ok(!fixture.nodes().some(node => node.props?.class === 'professional-any'));
        const professional = fixture.nodes().find(node => node.tagName === 'BUTTON' && node.props.class === 'professional-main');
        await fixture.click(professional); t.mock.timers.tick(200); await flush();
        assert.equal(searches(fixture).length, 1); assert.equal(searches(fixture)[0].body.staff, 'avery');
        assert.deepEqual(searches(fixture)[0].body.services, ['cut']);
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});

test('restoring a service selection clears a remembered professional when the policy now uses first available', async t => {
    clock(t); const fixture = mount({ staffPreference: 'any_only', saved: { flow: 'saved', secret: 'saved-secret', expires_at: new Date(Date.now() + 7200000).toISOString(), selection: { location: 'main', services: ['cut'], staff: 'avery', date } } });
    try {
        await flush(); await fixture.click(fixture.button('Choose a time'));
        assert.equal(searches(fixture)[0].body.staff, null);
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});

test('availability cooldown blocks automatic date searches and manual retries while preserving selected services', async t => {
    clock(t); let fail = true;
    const fixture = mount({ post: url => { if (url === 'public.booking.search' && fail) throw { response: { status: 429, headers: { 'retry-after': '7' }, data: {} } }; } });
    try {
        await times(fixture); assert.match(fixture.text(), /Try again in 7 seconds/); assert.equal(searches(fixture).length, 1);
        await fixture.input('booking-date', '2035-10-10'); t.mock.timers.tick(200); await flush();
        await fixture.click(fixture.button('Check available times'), true); assert.equal(searches(fixture).length, 1);
        fail = false; t.mock.timers.tick(7000); await flush();
        assert.equal(searches(fixture).length, 1, 'Cooldown expiry must not cause an automatic retry');
        await fixture.click(fixture.button('Check available times'));
        assert.equal(searches(fixture).length, 2); assert.deepEqual(searches(fixture)[1].body.services, ['cut']);
        assert.equal(searches(fixture)[1].body.from_date, '2035-10-10');
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});

test('confirmation cooldown prevents duplicate confirm and release calls and keeps entered details', async t => {
    clock(t);
    const fixture = mount({ post: url => { if (url === 'public.booking.confirm') throw { response: { status: 429, headers: { 'retry-after': '9' }, data: {} } }; } });
    try {
        await times(fixture); await fixture.click(fixture.nodes().find(node => node.props?.class === 'booking-slot'));
        await fixture.input('client-name', 'Morgan Reed'); await fixture.input('client-email', 'morgan@example.test'); await fixture.input('client-mobile', '+15555550123');
        await fixture.submit();
        const consent = fixture.nodes().find(node => node.tagName === 'INPUT' && node.props.required !== undefined && node.props.type === 'checkbox');
        consent.checked = true; await consent.listeners.change({ target: consent }); await flush();
        await fixture.submit(); assert.equal(fixture.calls.filter(call => call.url === 'public.booking.confirm').length, 1);
        await fixture.submit(); await fixture.click(fixture.button('Change time'), true);
        assert.equal(fixture.calls.filter(call => call.url === 'public.booking.confirm').length, 1);
        assert.equal(fixture.calls.filter(call => call.url === 'public.booking.release').length, 0);
        assert.match(fixture.text(), /Morgan Reed/); assert.match(fixture.text(), /morgan@example.test/);
        assert.match(fixture.text(), /Try again in 9 seconds/);
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});

test('private preview can explore services and profiles without storage or flow side effects', async t => {
    clock(t); const fixture = mount({ preview: true });
    try {
        await selectCut(fixture); await fixture.click(fixture.button('Choose a time'), true); t.mock.timers.tick(5000); await flush();
        assert.match(fixture.text(), /Private preview/); assert.match(fixture.text(), /Senior stylist/);
        assert.equal(fixture.calls.length, 0); assert.equal(fixture.storage.length, 0);
        assert.ok(fixture.button('Choose a time').props.disabled);
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});
