import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createSSRApp, createRenderer, nextTick, h, reactive } from 'vue';
import { renderToString } from '@vue/server-renderer';

const vueUrl = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
globalThis.clientReviewTest = { h, forms: [] };
const stub = { setup(props, { slots }) { return () => h('div', slots.default?.()); } };
globalThis.clientReviewTest.component = stub;
globalThis.clientReviewTest.Button = { inheritAttrs: false, setup(props, { attrs, slots }) { return () => h('button', attrs, slots.default?.()); } };
globalThis.clientReviewTest.useForm = data => {
    const form = reactive({ ...data, errors: {}, processing: false, post() {} });
    globalThis.clientReviewTest.forms.push(form); return form;
};
async function load(path) {
    const { descriptor } = parse(await readFile(resolve(path), 'utf8'));
    let code = compileScript(descriptor, { id: path, inlineTemplate: true }).content.replace(/from (['"])vue\1/g, `from '${vueUrl}'`);
    code = code.replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/(?:solid|outline)';/, (_, names) => names.split(',').map(name => `const ${name.trim()} = globalThis.clientReviewTest.component;`).join('\n'))
        .replace(/import \{ useForm \} from '@inertiajs\/vue3';/, 'const useForm = globalThis.clientReviewTest.useForm;')
        .replace("import axios from 'axios';", 'const axios = { post: (...args) => globalThis.clientReviewTest.post(...args), get: (...args) => globalThis.clientReviewTest.get(...args) };')
        .replace(/import AppNotice from '@\/[^']+';/, 'const AppNotice = globalThis.clientReviewTest.Notice;')
        .replace(/import AppButton from '@\/[^']+';/, 'const AppButton = globalThis.clientReviewTest.Button;')
        .replace(/import (\w+) from '@\/[^']+';/g, (_, name) => `const ${name} = globalThis.clientReviewTest.component;`);
    return (await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
}
globalThis.clientReviewTest.Notice = await load('resources/js/Components/Product/AppNotice.vue');
const Form = await load('resources/js/Components/Booking/ClientReviewForm.vue');
const Section = await load('resources/js/Components/Booking/ClientReviewsSection.vue');
async function render(Component, props) {
    const app = createSSRApp(Component, props); app.provide('route', (name, args) => `/${name}/${args?.join('/') || ''}`); return renderToString(app);
}
function mount(Component, props) {
    globalThis.document ||= { activeElement: null };
    globalThis.Document ||= class Document {};
    globalThis.ShadowRoot ||= class ShadowRoot {};
    globalThis.clientReviewTest.forms = [];
    const root = { children: [] };
    const renderer = createRenderer({
        createElement: type => ({ type, tagName: type.toUpperCase(), props: {}, children: [], listeners: {}, getRootNode: () => globalThis.document, addEventListener(name, fn) { this.listeners[name] = fn; }, removeEventListener() {} }),
        createText: text => ({ type: '#text', text }), createComment: text => ({ type: '#comment', text }),
        insert: (node, parent, anchor) => { node.parent = parent; const at = anchor ? parent.children.indexOf(anchor) : -1; if (at < 0) parent.children.push(node); else parent.children.splice(at, 0, node); },
        remove: node => { if (node.parent) node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp: (node, key, old, value) => { node.props[key] = value; node[key] = value; },
    });
    const app = renderer.createApp(Component, props); app.provide('route', (name, args) => `/${name}/${args?.join('/') || ''}`); app.mount(root);
    const nodes = () => { const result = []; const walk = node => { result.push(node); node.children?.forEach(walk); }; walk(root); return result; };
    return { app, nodes, form: globalThis.clientReviewTest.forms[0], text: () => nodes().map(node => node.text || '').join(' '), submit: () => nodes().find(node => node.type === 'form').props.onSubmit({ preventDefault() {} }) };
}
test('review form starts with private consent and offers useful rating and detail labels', async () => {
    globalThis.clientReviewTest.forms = [];
    const html = await render(Form, { review: { eligible: true, saved: null }, actionToken: 'private-token' });
    assert.equal(globalThis.clientReviewTest.forms[0].public_consent, false);
    assert.equal(globalThis.clientReviewTest.forms[0].rating, null);
    assert.match(html, /Send private feedback/); assert.match(html, /Tell us about your experience/);
    assert.match(html, /1 out of 5 — Very disappointing/); assert.match(html, /5 out of 5 — Excellent/);
});
test('public section hides when there are no consented reviews and escapes detailed text', async () => {
    const empty = await render(Section, { summary: { count: 0, average: null, items: [] } });
    assert.doesNotMatch(empty, /Client reviews|out of 5/);
    const html = await render(Section, { summary: { count: 1, average: 1, items: [{ id: 'one', rating: 1, author_initials: 'JS', date: '2026-10-29', body: '<script>unsafe</script>' }] } });
    assert.match(html, /1 out of 5/); assert.match(html, /Verified visit/); assert.match(html, /&lt;script&gt;unsafe&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>/);
});
test('completed review form offers withdrawal for a public review and preserves the review text', async () => {
    const html = await render(Form, { review: { eligible: true, saved: { rating: 4, body: 'Thoughtful service', public_consent: true } }, actionToken: 'private-token' });
    assert.match(html, /Keep my feedback private/); assert.match(html, /Thoughtful service/); assert.doesNotMatch(html, /Publish my review/);
});

test('actual review form retains entered feedback and honors Retry-After before safe retry', async t => {
    t.mock.timers.enable({ apis: ['setInterval'] });
    let calls = 0;
    globalThis.clientReviewTest.post = async () => { calls++; if (calls === 1) throw { response: { status: 429, headers: { 'retry-after': '2' } } }; return { data: { review: { saved: { rating: 4, body: 'My careful feedback', public_consent: false } } } }; };
    const fixture = mount(Form, { review: { eligible: true, saved: null }, actionToken: 'test-link' });
    try {
        fixture.form.rating = 4; fixture.form.body = 'My careful feedback'; await nextTick();
        await fixture.submit(); await nextTick();
        assert.equal(fixture.form.body, 'My careful feedback'); assert.match(fixture.text(), /Please wait before sending again/);
        assert.match(fixture.text(), /2 seconds/); await fixture.submit(); assert.equal(calls, 1);
        t.mock.timers.tick(2000); await nextTick(); await fixture.submit(); await nextTick();
        assert.equal(calls, 2); assert.match(fixture.text(), /Thank you for your feedback/); assert.match(fixture.text(), /My careful feedback/);
    } finally { fixture.app.unmount(); t.mock.timers.reset(); }
});

test('actual review form blocks duplicate in-flight clicks and stops after expired-link failure', async () => {
    let reject, calls = 0;
    globalThis.clientReviewTest.post = () => { calls++; return new Promise((_, failed) => { reject = failed; }); };
    const fixture = mount(Form, { review: { eligible: true, saved: null }, actionToken: 'test-link' });
    try {
        fixture.form.rating = 2; fixture.form.body = 'Keep my draft'; await nextTick();
        const saving = fixture.submit(); await fixture.submit(); assert.equal(calls, 1);
        reject({ response: { status: 410 } }); await saving; await nextTick();
        assert.equal(fixture.form.body, 'Keep my draft'); assert.match(fixture.text(), /fresh review link/);
        await fixture.submit(); assert.equal(calls, 1);
    } finally { fixture.app.unmount(); }
});

test('actual more-reviews request preserves visible reviews after a failed load', async () => {
    globalThis.clientReviewTest.get = async () => { throw Error('offline'); };
    const fixture = mount(Section, { summary: { count: 13, average: 4, items: [{ id: 'one', rating: 4, body: 'Already visible', author_initials: 'AB', date: '2026-10-29' }], next_url: '/reviews?before=one' } });
    try {
        await fixture.nodes().find(node => node.type === 'button').props.onClick(); await nextTick();
        assert.match(fixture.text(), /Already visible/); assert.match(fixture.text(), /reviews already shown are still available/);
        assert.ok(fixture.nodes().some(node => node.props?.role === 'alert'));
    } finally { fixture.app.unmount(); }
});
