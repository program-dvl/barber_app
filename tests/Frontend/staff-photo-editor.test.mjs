import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, reactive, h } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const { descriptor } = parse(await readFile(resolve('resources/js/Components/Team/StaffPhotoEditor.vue'), 'utf8'));
const source = compileScript(descriptor, { id: 'staff-photo-test', inlineTemplate: true }).content
    .replace(/from (['"])vue\1/g, `from '${vue}'`)
    .replace("import { useForm } from '@inertiajs/vue3';", 'const useForm = data => globalThis.staffPhotoTest.form(data);')
    .replace(/import AppButton from '[^']+';/, "const AppButton={inheritAttrs:false,setup(props,{attrs,slots}){return ()=>testH('button',attrs,slots.default?.())}};")
    .replace(/import AppNotice from '[^']+';/, "const AppNotice={setup(props,{attrs,slots}){return ()=>testH('aside',attrs,[testH('p',attrs.description),slots.actions?.()])}};")
    .replace(/import StaffAvatar from '[^']+';/, "const StaffAvatar={props:['name','photoUrl'],setup(props){return ()=>testH('img',{src:props.photoUrl})}};");
const Editor = (await import(`data:text/javascript;base64,${Buffer.from(`import { h as testH } from '${vue}';\n${source}`).toString('base64')}`)).default;
const flush = async () => { for (let i = 0; i < 8; i++) await nextTick(); };
function mount() {
    const root = { children: [] }, calls = [], events = [];
    const person = reactive({ public_id: 'member', display_name: 'Avery', photo_url: null, profile_revision: 'revision-one' });
    let form;
    globalThis.staffPhotoTest = { form(data) {
        const defaults = { ...data };
        form = reactive({ ...data, errors: {}, processing: false,
            reset(...fields) { for (const key of fields) this[key] = defaults[key]; }, clearErrors() { this.errors = {}; },
            transform(callback) { this.transformer = callback; return this; },
            post(url, options) { calls.push({ method: 'post', url, options, revision: this.profile_revision, photo: this.photo }); },
            delete(url, options) { calls.push({ method: 'delete', url, options, revision: this.profile_revision }); },
        }); return form;
    } };
    globalThis.route = name => name;
    const renderer = createRenderer({
        createElement: type => ({ type, props: {}, children: [] }), createText: text => ({ text }), createComment: () => ({ text: '' }),
        insert(child, parent, anchor) { if (child.parent) child.parent.children = child.parent.children.filter(node => node !== child); child.parent = parent; const index = parent.children.indexOf(anchor); if (index < 0) parent.children.push(child); else parent.children.splice(index, 0, child); },
        remove(node) { node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp: (node, key, before, after) => node.props[key] = after,
    });
    const state = reactive({ business: { public_id: 'business' }, person, disabled: false });
    const app = renderer.createApp({ render: () => h(Editor, { ...state, onDirty: value => events.push(['dirty', value]), onBusy: value => events.push(['busy', value]), onSaved: () => events.push(['saved']), onRefresh: () => events.push(['refresh']) }) }); app.mount(root);
    const nodes = () => { const all = []; function walk(node) { all.push(node); node.children?.forEach(walk); } walk(root); return all; };
    const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ');
    return { app, state, person, calls, events, form: () => form, nodes, text: () => text(root), choose: file => nodes().find(node => node.type === 'input').props.onChange({ target: { files: [file], value: '' } }), button: label => nodes().find(node => node.type === 'button' && text(node).includes(label)) };
}

test('photo choice waits for explicit save and preserves the chosen file after a connection failure', async t => {
    t.mock.method(URL, 'createObjectURL', () => 'blob:portrait');
    t.mock.method(URL, 'revokeObjectURL', () => {});
    const fixture = mount();
    try {
        const file = { type: 'image/jpeg', size: 1024 };
        fixture.choose(file); await flush(); assert.equal(fixture.calls.length, 0);
        assert.deepEqual(fixture.events.at(-1), ['dirty', true]);
        fixture.button('Save photo').props.onClick(); assert.equal(fixture.calls.length, 1);
        fixture.calls[0].options.onNetworkError(); await flush();
        assert.match(fixture.text(), /chosen photo is still here/); assert.deepEqual(fixture.form().photo, file);
        assert.ok(fixture.nodes().some(node => node.type === 'img' && node.props.src === 'blob:portrait'));
    } finally { fixture.app.unmount(); }
});

test('refresh after stale revision retains the photo and a later save binds the current profile', async t => {
    t.mock.method(URL, 'createObjectURL', () => 'blob:portrait'); t.mock.method(URL, 'revokeObjectURL', () => {});
    const fixture = mount();
    try {
        fixture.choose({ type: 'image/png', size: 1024 }); await flush();
        fixture.form().errors = { profile_revision: 'This profile changed. Refresh the team member before changing their photo.' }; await flush();
        fixture.button('Refresh profile').props.onClick(); assert.deepEqual(fixture.events.at(-1), ['refresh']);
        fixture.person.profile_revision = 'revision-two'; await flush();
        fixture.button('Save photo').props.onClick(); assert.equal(fixture.calls[0].revision, 'revision-two');
        fixture.calls[0].options.onSuccess(); await flush(); assert.equal(fixture.form().photo, null);
        assert.deepEqual(fixture.events.find(event => event[0] === 'saved'), ['saved']);
    } finally { fixture.app.unmount(); }
});

test('invalid files leave the saved portrait unchanged and discard releases a selected preview', async t => {
    t.mock.method(URL, 'createObjectURL', () => 'blob:portrait'); const revoke = t.mock.method(URL, 'revokeObjectURL', () => {});
    const fixture = mount();
    try {
        fixture.person.photo_url = '/current-portrait';
        fixture.choose({ type: 'image/svg+xml', size: 1024 }); await flush();
        assert.match(fixture.text(), /JPEG, PNG or WebP photo smaller than 5 MB/); assert.equal(fixture.form().photo, null);
        assert.ok(fixture.nodes().some(node => node.type === 'img' && node.props.src === '/current-portrait'));
        fixture.choose({ type: 'image/webp', size: 1024 }); await flush();
        fixture.button('Discard photo').props.onClick(); await flush();
        assert.equal(fixture.form().photo, null); assert.equal(revoke.mock.callCount(), 1);
        assert.deepEqual(fixture.events.at(-1), ['dirty', false]);
    } finally { fixture.app.unmount(); }
});
