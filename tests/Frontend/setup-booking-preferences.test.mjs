import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, h, nextTick, reactive } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
globalThis.setupBookingPreferencesTest = {
    components: {
        AppButton: { inheritAttrs: false, setup(props, { attrs, slots }) { return () => h('button', attrs, slots.default?.()); } },
        AppSelect: { props: ['modelValue', 'disabled'], emits: ['update:modelValue'], setup(props, { attrs, slots, emit }) { return () => h('select', { ...attrs, disabled: props.disabled, value: props.modelValue, onChange: event => emit('update:modelValue', event.target.value) }, slots.default?.()); } },
    },
};
async function load(path) {
    const { descriptor } = parse(await readFile(resolve(path), 'utf8'));
    const source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replace(/from (['"])vue\1/g, `from '${vue}'`)
        .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/g, (_, names) => names.split(',').map(name => `const ${name.trim()} = {render(){return null}};`).join('\n'))
        .replace(/import (\w+) from '@\/Components\/Product\/[^']+';/g, (_, name) => `const ${name} = globalThis.setupBookingPreferencesTest.components.${name};`);
    return (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
}
globalThis.setupBookingPreferencesTest.components.FormField = await load('resources/js/Components/Product/FormField.vue');
globalThis.setupBookingPreferencesTest.components.AppNotice = await load('resources/js/Components/Product/AppNotice.vue');
const Preferences = await load('resources/js/Components/Setup/SetupBookingPreferences.vue');
const flush = async () => { await nextTick(); await nextTick(); };
function mount(initial = {}) {
    const root = { children: [] }, saves = [];
    const form = reactive({ appointment_interval_minutes: 15, online_booking_enabled: true, online_staff_preference: 'any_or_preferred', online_price_display: 'service_setting', online_new_client_rule: 'allow', staff_gender_request_enabled: true, cancellation_cutoff_minutes: 1440, waitlist_offer_batch_size: 1, public_link_ttl_minutes: 10080, setup_revision: 'retained-revision', errors: {}, processing: false, ...initial });
    const renderer = createRenderer({
        createElement: type => ({ type, tagName: type.toUpperCase(), props: {}, children: [], listeners: {}, addEventListener(name, handler) { this.listeners[name] = handler; }, removeEventListener() {} }),
        createText: text => ({ text }), createComment: () => ({ text: '' }),
        insert(node, parent, anchor) { if (node.parent) node.parent.children = node.parent.children.filter(child => child !== node); node.parent = parent; const index = parent.children.indexOf(anchor); if (index < 0) parent.children.push(node); else parent.children.splice(index, 0, node); },
        remove(node) { node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp(node, key, before, after) { node.props[key] = after; node[key] = after; },
    });
    const app = renderer.createApp({ render: () => h(Preferences, { form, onSave: () => saves.push({ ...form }) }) });
    app.mount(root);
    const nodes = () => { const all = []; function walk(node) { all.push(node); node.children?.forEach(walk); } walk(root); return all; };
    const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ').replace(/\s+/g, ' ');
    const field = id => nodes().find(node => node.props?.id === id);
    const change = async (id, value) => { const node = field(id); assert.ok(node, `Expected ${id}`); node.value = value; node.props.onChange({ target: node }); await flush(); };
    const submit = async () => { const formNode = nodes().find(node => node.type === 'form'); await formNode.props.onSubmit({ preventDefault() {}, stopPropagation() {} }); await flush(); };
    return { app, form, saves, nodes, field, change, submit, text: () => text(root), nodeText: text };
}

test('plain booking choices explain their actual effects without changing saved policy values', async () => {
    const view = mount();
    try {
        assert.match(view.text(), /Appointment start interval/);
        assert.match(view.text(), /Service lengths stay the same/);
        assert.match(view.text(), /Use each service’s price setting/);
        assert.match(view.text(), /New and existing clients/);
        assert.doesNotMatch(view.text(), /Allow staff gender requests|Per service setting/);
        await view.change('bs-online-prices', 'exact');
        assert.match(view.text(), /Prices may still show “from” when team member prices vary/);
        await view.change('bs-online-prices', 'from');
        assert.match(view.text(), /Adds “from” before every listed price/);
        await view.change('bs-online-clients', 'consultation_only');
        assert.match(view.text(), /marked “Consultation service” in Services/);
        await view.change('bs-staff-choice', 'preferred_required');
        assert.match(view.text(), /must pick a team member before seeing available times/);
        assert.equal(view.saves.length, 0, 'Choosing a preference must not save automatically');
        await view.submit();
        assert.equal(view.saves.length, 1);
        assert.equal(view.saves[0].online_price_display, 'from');
        assert.equal(view.saves[0].online_new_client_rule, 'consultation_only');
        assert.equal(view.saves[0].online_staff_preference, 'preferred_required');
        assert.equal(view.saves[0].staff_gender_request_enabled, true, 'Hidden unsupported flag must retain its saved value');
        assert.equal(view.saves[0].setup_revision, 'retained-revision');
    } finally { view.app.unmount(); }
});

test('private link and waitlist help distinguish the public page and first valid claim', () => {
    const view = mount({ cancellation_cutoff_minutes: 120, public_link_ttl_minutes: 180 });
    try {
        assert.match(view.text(), /Waitlist & appointment links/);
        assert.match(view.text(), /matching waitlist clients. The first to confirm gets the time/);
        assert.match(view.text(), /Private appointment links expire after/);
        assert.match(view.text(), /viewing or managing an appointment/);
        assert.match(view.text(), /does not affect your public booking link/);
        assert.equal(view.nodes().find(node => node.type === 'details').props.open, false);
        assert.ok(view.field('bs-online-deadline').children.some(node => node.props?.value === 120 && view.nodeText(node).includes('120 minutes before (current)')));
        assert.ok(view.field('bs-appointment-link-validity').children.some(node => node.props?.value === 180 && view.nodeText(node).includes('180 minutes (current)')));
        assert.equal(view.form.cancellation_cutoff_minutes, 120);
        assert.equal(view.form.public_link_ttl_minutes, 180);
    } finally { view.app.unmount(); }
});

test('advanced validation opens its section and connects error and help to the actual control', async () => {
    const view = mount({ errors: { public_link_ttl_minutes: 'Choose a link validity between 15 minutes and 30 days.', online_booking_enabled: 'Review your page before accepting bookings.' } });
    try {
        assert.equal(view.nodes().find(node => node.type === 'details').props.open, true);
        assert.equal(view.field('bs-appointment-link-validity').props['aria-invalid'], 'true');
        assert.equal(view.field('bs-appointment-link-validity').props['aria-describedby'], 'bs-appointment-link-validity-hint bs-appointment-link-validity-error');
        assert.equal(view.field('bs-appointment-link-validity-error').props.role, 'alert');
        assert.match(view.nodeText(view.field('bs-appointment-link-validity-error')), /between 15 minutes and 30 days/);
        assert.equal(view.field('bs-online-booking').props['aria-invalid'], true);
        assert.equal(view.field('bs-online-booking').props['aria-describedby'], 'bs-online-booking-hint bs-online-booking-error');
        view.form.errors = { setup_revision: 'Your settings changed in another window. Reload before saving.' };
        await flush();
        assert.match(view.text(), /Booking preferences changed/);
        assert.match(view.text(), /Reload before saving/);
    } finally { view.app.unmount(); }
});

test('saving disables editable controls and rejects repeat submissions', async () => {
    const view = mount();
    try {
        await view.submit(); assert.equal(view.saves.length, 1);
        view.form.processing = true; await flush();
        assert.ok(view.nodes().filter(node => ['input', 'select', 'button'].includes(node.type)).every(node => node.props.disabled));
        await view.submit(); assert.equal(view.saves.length, 1);
        assert.match(view.text(), /Save booking preferences/);
    } finally { view.app.unmount(); }
});
