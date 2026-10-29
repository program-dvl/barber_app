import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, reactive, h } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const { descriptor } = parse(await readFile(resolve('resources/js/Components/Setup/BrandImageEditor.vue'), 'utf8'));
const source = compileScript(descriptor, { id: 'staff-photo-test', inlineTemplate: true }).content
    .replace(/from (['"])vue\1/g, `from '${vue}'`)
    .replace("import { useForm } from '@inertiajs/vue3';", 'const useForm = data => globalThis.staffPhotoTest.form(data);')
    .replace("import axios from 'axios';", 'const axios={get:(...args)=>globalThis.staffPhotoTest.get(...args)};')
    .replace(/import AppButton from '[^']+';/, "const AppButton={inheritAttrs:false,setup(props,{attrs,slots}){return ()=>testH('button',attrs,slots.default?.())}};")
    .replace(/import AppNotice from '[^']+';/, "const AppNotice={setup(props,{attrs,slots}){return ()=>testH('aside',attrs,[testH('p',attrs.description),slots.actions?.()])}};")
    .replace(/import AppDialog from '[^']+';/, "const AppDialog={setup(props,{slots,expose}){expose({open(){},close(){}});return ()=>testH('div',slots.default?.())}};")
    .replace(/import \{ PhotoIcon \} from '@heroicons\/vue\/24\/outline';/, 'const PhotoIcon={render(){return null}};');
const Editor = (await import(`data:text/javascript;base64,${Buffer.from(`import { h as testH } from '${vue}';\n${source}`).toString('base64')}`)).default;
const flush = async () => { for (let i = 0; i < 8; i++) await nextTick(); };
function mount() {
    const root = { children: [] }, calls = [], events = [];
    const person = reactive({ public_id: 'member' });
    let form;
    globalThis.staffPhotoTest = { form(data) {
        const defaults = { ...data };
        form = reactive({ ...data, errors: {}, processing: false,
            reset(...fields) { for (const key of fields) this[key] = defaults[key]; }, clearErrors() { this.errors = {}; },
            transform(callback) { this.transformer = callback; return this; },
            post(url, options) { calls.push({ method: 'post', url, options, revision: this.existing_image, photo: this.asset }); },
            delete(url, options) { calls.push({ method: 'delete', url, options, revision: this.existing_image }); },
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
    const state = reactive({ businessId:'business', kind:'logo', title:'Business logo', help:'PNG, JPEG or WebP, up to 5 MB.', savedPath:'configuration/logo.png', disabled:false });
    const app = renderer.createApp({ render: () => h(Editor, { ...state, onDirty: value => events.push(['dirty', value]), onBusy: value => events.push(['busy', value]), onSaved: () => events.push(['saved']), onRefresh: () => events.push(['refresh']) }) }); app.provide('route', (name,args) => `/${name}/${Array.isArray(args)?args.join('/'):args}`); app.mount(root);
    const nodes = () => { const all = []; function walk(node) { all.push(node); node.children?.forEach(walk); } walk(root); return all; };
    const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ');
    return { app, state, person, calls, events, form: () => form, nodes, text: () => text(root), choose: file => nodes().find(node => node.type === 'input').props.onChange({ target: { files: [file], value: '' } }), button: label => nodes().find(node => node.type === 'button' && text(node).includes(label)) };
}

test('branding shows the saved private image and requires a preview before explicit save', async t => {
    t.mock.method(URL, 'createObjectURL', () => 'blob:branding'); t.mock.method(URL, 'revokeObjectURL', () => {});
    const fixture = mount();
    try {
        assert.ok(fixture.nodes().some(node => node.type === 'img' && node.props.src.includes('business.configuration.booking-preview.media')));
        fixture.choose({ type:'image/png', size:1024 }); await flush();
        assert.equal(fixture.calls.length, 0); assert.match(fixture.text(), /Preview only/);
        assert.ok(fixture.nodes().some(node => node.type === 'img' && node.props.src === 'blob:branding'));
        fixture.button('Save image').props.onClick(); assert.equal(fixture.calls.length, 1);
        assert.equal(fixture.calls[0].revision, 'configuration/logo.png');
        fixture.calls[0].options.onSuccess(); await flush(); assert.equal(fixture.form().asset, null);
        assert.match(fixture.text(), /Business logo saved/);
    } finally { fixture.app.unmount(); }
});

test('branding preserves the chosen preview on failure and discard restores the saved image', async t => {
    t.mock.method(URL, 'createObjectURL', () => 'blob:branding'); const revoke=t.mock.method(URL, 'revokeObjectURL', () => {});
    const fixture=mount();
    try {
        fixture.choose({type:'image/jpeg',size:1024}); await flush(); fixture.button('Save image').props.onClick();
        fixture.calls[0].options.onNetworkError(); await flush();
        assert.match(fixture.text(), /chosen image is still here/); assert.ok(fixture.form().asset);
        fixture.button('Discard').props.onClick(); await flush(); assert.equal(fixture.form().asset,null);
        assert.equal(revoke.mock.callCount(),1);
        assert.ok(fixture.nodes().some(node=>node.type==='img' && node.props.src.includes('configuration%2Flogo.png')));
    } finally {fixture.app.unmount();}
});

test('branding rejects vectors without sending a request or replacing the saved image', async () => {
    const fixture=mount();
    try {
        fixture.choose({type:'image/svg+xml',size:1024}); await flush();
        assert.equal(fixture.calls.length,0); assert.equal(fixture.form().asset,null);
        assert.match(fixture.text(), /PNG, JPEG or WebP image smaller than 5 MB/);
        assert.ok(fixture.nodes().some(node=>node.type==='img' && node.props.src.includes('booking-preview.media')));
    } finally {fixture.app.unmount();}
});

test('stale image recovery refreshes the saved version while keeping the selected preview', async t => {
    t.mock.method(URL,'createObjectURL',()=> 'blob:branding'); t.mock.method(URL,'revokeObjectURL',()=>{});
    const fixture=mount();
    try {
        fixture.choose({type:'image/jpeg',size:1024}); await flush();
        fixture.form().errors={existing_image:'This image changed in another window.'}; await flush();
        globalThis.staffPhotoTest.get=async()=>({data:{props:{business:{logo_path:'configuration/newer.png'}}}});
        await fixture.button('Refresh saved image').props.onClick(); await flush();
        assert.equal(fixture.form().existing_image,'configuration/newer.png');
        assert.ok(fixture.form().asset); assert.ok(fixture.nodes().some(node=>node.type==='img' && node.props.src==='blob:branding'));
        fixture.button('Save image').props.onClick(); assert.equal(fixture.calls[0].revision,'configuration/newer.png');
    }finally {fixture.app.unmount();}
});
