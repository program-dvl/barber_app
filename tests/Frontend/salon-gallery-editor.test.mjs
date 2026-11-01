import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, h, nextTick, reactive } from 'vue';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const { descriptor: noticeDescriptor } = parse(await readFile(resolve('resources/js/Components/Product/AppNotice.vue'), 'utf8'));
const noticeSource = compileScript(noticeDescriptor, { id: 'salon-gallery-shared-notice-test', inlineTemplate: true }).content
    .replace(/from (['"])vue\1/g, `from '${vue}'`)
    .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/, (_, names) => names.split(',').map(name => `const ${name.trim()} = {render(){return testH('i')}};`).join('\n'));
globalThis.salonGallerySharedNotice = (await import(`data:text/javascript;base64,${Buffer.from(`import { h as testH } from '${vue}';\n${noticeSource}`).toString('base64')}`)).default;
const { descriptor } = parse(await readFile(resolve('resources/js/Components/Setup/SalonGalleryEditor.vue'), 'utf8'));
const source = compileScript(descriptor, { id: 'salon-gallery-test', inlineTemplate: true }).content
    .replace(/from (['"])vue\1/g, `from '${vue}'`)
    .replace("import axios from 'axios';", 'const axios = new Proxy({}, {get: (_, method) => (...args) => globalThis.galleryTest.request(method, ...args)});')
    .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/, (_, names) => names.split(',').map(name => `const ${name.trim()} = {render(){return testH('i')}};`).join('\n'))
    .replace(/import AppButton from '[^']+';/, "const AppButton={inheritAttrs:false,setup(props,{attrs,slots}){return ()=>testH('button',attrs,slots.default?.())}};")
    .replace(/import AppNotice from '[^']+';/, 'const AppNotice=globalThis.salonGallerySharedNotice;')
    .replace(/import AppDialog from '[^']+';/, "const AppDialog={setup(props,{expose}){expose({open(){},close(){}});return ()=>testH('dialog')}};")
    .replace(/import FieldError from '[^']+';/, "const FieldError={render(){return testH('span')}};")
    .replaceAll('URL.createObjectURL(', 'globalThis.galleryTest.createPreview(')
    .replaceAll('URL.revokeObjectURL(', 'globalThis.galleryTest.revokePreview(')
    .replace('new FormData()', 'new globalThis.galleryTest.FormData()');
const Editor = (await import(`data:text/javascript;base64,${Buffer.from(`import { h as testH } from '${vue}';\n${source}`).toString('base64')}`)).default;
globalThis.Document ??= class Document {};
globalThis.ShadowRoot ??= class ShadowRoot {};
const flush = async () => { for (let i = 0; i < 12; i++) await nextTick(); };
const gallery = revision => ({ revision, limit: 8, photos: [{ public_id: 'one', caption: 'First', url: '/photo-one' }, { public_id: 'two', caption: 'Second', url: '/photo-two' }] });

function mount(active = true, options = {}) {
    const root = { children: [] }, calls = [], previews = [], revoked = [], events = [], state = reactive({ active, businessId: 'business', disabled: false });
    let server = options.server || gallery('revision-one'), loadError = options.loadError;
    globalThis.galleryTest = {
        FormData: class { constructor(){this.values=new Map();} append(key,value){this.values.set(key,value);} },
        createPreview(file){const url=`blob:preview-${previews.length+1}`;previews.push({file,url});return url;},revokePreview:url=>revoked.push(url),
        request(method, url, payload) {
            if (method === 'get') {
                if (options.deferredLoad) return new Promise((resolve,reject)=>calls.push({method,url,resolve,reject}));
                calls.push({method,url});return loadError ? Promise.reject(loadError) : Promise.resolve({data:server});
            }
            return new Promise((resolve,reject)=>calls.push({method,url,payload,resolve,reject}));
        },
    };
    const renderer = createRenderer({
        createElement: type => ({ type, props: {}, children: [], addEventListener(){}, getRootNode(){return root;} }), createText: text => ({ text }), createComment: () => ({text:''}),
        insert(child,parent,anchor) { if(child.parent) child.parent.children=child.parent.children.filter(node=>node!==child); child.parent=parent; const index=parent.children.indexOf(anchor); if(index<0) parent.children.push(child); else parent.children.splice(index,0,child); },
        remove(node){node.parent.children=node.parent.children.filter(child=>child!==node);},
        setText:(node,text)=>node.text=text, setElementText:(node,text)=>{node.text=text;node.children=[];},
        parentNode:node=>node.parent,nextSibling:node=>node.parent?.children[node.parent.children.indexOf(node)+1],patchProp:(node,key,before,after)=>node.props[key]=after,
    });
    const app=renderer.createApp({render:()=>h(Editor,{...state,onDirty:value=>events.push(['dirty',value])})}); app.provide('route',name=>name); app.mount(root);
    const nodes=()=>{const all=[];function visit(node){all.push(node);node.children?.forEach(visit);}visit(root);return all;};
    const text=node=>[node.text||'',...(node.children||[]).map(text)].join(' ');
    return {app,state,calls,nodes,previews,revoked,events,text:()=>text(root),server:value=>server=value,loadError:value=>loadError=value,button:label=>nodes().find(node=>node.type==='button'&&text(node).includes(label)),field:id=>nodes().find(node=>node.props?.id===id),choose:files=>nodes().find(node=>node.props?.id==='salon-gallery-file').props.onChange({target:{files:(Array.isArray(files)?files:[files]).map(file=>({type:'image/png',size:1024,...file}))}}),edit:id=>nodes().find(node=>node.type==='button'&&node.props['aria-label']===`Edit photo ${id}`).props.onClick(),submit:()=>nodes().find(node=>node.type==='form').props.onSubmit({preventDefault(){}})};
}

test('optional gallery loads when opened and waits for an explicit photo save', async () => {
    const fixture=mount(false);
    try {
        await flush();assert.equal(fixture.calls.length,0);
        fixture.state.active=true;await flush();assert.equal(fixture.calls.length,1);
        fixture.choose({name:'salon.png'});await flush();assert.equal(fixture.calls.length,1);
        fixture.submit();fixture.submit();await flush();assert.equal(fixture.calls.filter(call=>call.method==='post').length,1);
        const call=fixture.calls.at(-1);call.reject({});await flush();
        assert.match(fixture.text(),/remaining photos and descriptions are kept/);
        fixture.submit();assert.equal(fixture.calls.at(-1).payload.values.get('photo').name,'salon.png');
        assert.equal(fixture.calls.at(-1).payload.values.get('idempotency_key'),call.payload.values.get('idempotency_key'));
        fixture.calls.at(-1).resolve({data:gallery('revision-two')});await flush();
    } finally {fixture.app.unmount();}
});

test('a stale revision reload keeps selected work and movement preserves unsaved descriptions', async () => {
    const fixture=mount();
    try {
        await flush();fixture.choose({name:'salon.png'});fixture.edit(1);await flush();
        fixture.field('gallery-caption-one').props['onUpdate:modelValue']('Unsaved description');
        const move=fixture.nodes().find(node=>node.type==='button'&&node.props['aria-label']==='Move photo 1 later');
        move.props.onClick();await flush();const call=fixture.calls.at(-1);
        assert.equal(call.payload.caption,'First');
        call.reject({response:{status:422,data:{errors:{revision:['The gallery changed. Reload photos.']}}}});await flush();
        fixture.server(gallery('revision-two'));fixture.button('Reload photos').props.onClick();await flush();
        assert.equal(fixture.field('gallery-caption-one').value,'Unsaved description');
        fixture.submit();const upload=fixture.calls.at(-1);
        assert.equal(upload.payload.values.get('revision'),'revision-two');assert.equal(upload.payload.values.get('photo').name,'salon.png');
        upload.resolve({data:gallery('revision-three')});await flush();
    } finally {fixture.app.unmount();}
});

test('photo previews precede explicit upload and only one existing photo expands for editing', async () => {
    const fixture=mount();
    try {
        await flush();assert.equal(fixture.field('gallery-caption-one'),undefined);assert.equal(fixture.field('gallery-caption-two'),undefined);
        fixture.edit(1);await flush();assert.ok(fixture.field('gallery-caption-one'));assert.equal(fixture.field('gallery-caption-two'),undefined);
        fixture.field('gallery-caption-one').props['onUpdate:modelValue']('Kept while editing another photo');
        fixture.edit(2);await flush();assert.equal(fixture.field('gallery-caption-one'),undefined);assert.ok(fixture.field('gallery-caption-two'));
        fixture.edit(1);await flush();assert.equal(fixture.field('gallery-caption-one').value,'Kept while editing another photo');
        fixture.choose({name:'styling-area.png'});await flush();
        assert.match(fixture.text(),/styling-area.png/);assert.ok(fixture.nodes().some(node=>node.type==='img'&&node.props.src==='blob:preview-1'));
        assert.equal(fixture.calls.filter(call=>call.method==='post').length,0);
        fixture.choose({name:'other-photo.png'});await flush();assert.deepEqual(fixture.revoked,[]);
        assert.match(fixture.text(),/2 photos selected/);
        fixture.nodes().find(node=>node.props?.['aria-label']==='Remove styling-area.png from selection').props.onClick();await flush();
        assert.deepEqual(fixture.revoked,['blob:preview-1']);assert.match(fixture.text(),/1 photo selected/);
        fixture.nodes().find(node=>node.props?.['aria-label']==='Remove other-photo.png from selection').props.onClick();await flush();assert.deepEqual(fixture.revoked,['blob:preview-1','blob:preview-2']);
    } finally {fixture.app.unmount();}
});

test('a rate limit disables saving for Retry-After without discarding the selected photo', async t => {
    let time=1_000_000, tick;
    t.mock.method(Date,'now',()=>time);t.mock.method(globalThis,'setInterval',callback=>{tick=callback;return 1;});t.mock.method(globalThis,'clearInterval',()=>{});
    const fixture=mount();
    try {
        await flush();fixture.choose({name:'salon.png'});fixture.submit();
        fixture.calls.at(-1).reject({response:{status:429,headers:{'retry-after':'90'},data:{}}});await flush();
        assert.match(fixture.text(),/Wait 90s/);fixture.submit();assert.equal(fixture.calls.filter(call=>call.method==='post').length,1);
        time+=90_000;tick();await flush();fixture.submit();assert.equal(fixture.calls.at(-1).payload.values.get('photo').name,'salon.png');
        fixture.calls.at(-1).resolve({data:gallery('revision-two')});await flush();
    } finally {fixture.app.unmount();}
});

test('initial loading and an actually empty gallery have distinct clear states', async () => {
    const fixture=mount(true,{deferredLoad:true});
    try {
        assert.match(fixture.text(),/Loading your photos/);assert.doesNotMatch(fixture.text(),/unavailable|Photos are optional|Photos need attention/);
        assert.equal(fixture.field('salon-gallery-file'),undefined);
        fixture.calls[0].resolve({data:{photos:[],revision:'empty-revision',limit:8}});await flush();
        assert.match(fixture.text(),/Photos are optional. Clients can book without them/);assert.ok(fixture.field('salon-gallery-file'));
        assert.doesNotMatch(fixture.text(),/Loading your photos|couldn’t load|unavailable/);
    } finally {fixture.app.unmount();}
});

test('a platform gallery failure is not shown as an empty gallery or an impossible retry', async () => {
    const fixture=mount(true,{loadError:{response:{status:503,data:{code:'GALLERY_UNAVAILABLE',support_reference:'12345678-1234-1234-1234-123456789abc',support_email:'support@clipperdesk.com'}}}});
    try {
        await flush();assert.match(fixture.text(),/Salon photos are temporarily unavailable/);assert.match(fixture.text(),/other setup details are saved/);
        assert.match(fixture.text(),/Your photo gallery couldn’t open/);assert.match(fixture.text(),/Support reference: 12345678-1234-1234-1234-123456789abc/);
        assert.ok(fixture.nodes().some(node=>node.props?.role==='alert'));
        assert.doesNotMatch(fixture.text(),/Photos need attention|Photos are optional|Load photos|Reload photos/);assert.equal(fixture.field('salon-gallery-file'),undefined);
        const support=fixture.nodes().find(node=>node.type==='a');assert.match(support.props.href,/^mailto:support@clipperdesk.com\?subject=/);assert.match(decodeURIComponent(support.props.href),/12345678-1234-1234-1234-123456789abc/);
        fixture.state.active=false;await flush();fixture.state.active=true;await flush();assert.equal(fixture.calls.length,1);
    } finally {fixture.app.unmount();}
});

test('a connection failure offers one deliberate reload and clears the error after recovery', async () => {
    const fixture=mount(true,{loadError:{}});
    try {
        await flush();assert.match(fixture.text(),/Photos couldn’t load/);assert.doesNotMatch(fixture.text(),/Photos are optional/);assert.ok(fixture.button('Load photos'));
        fixture.loadError(null);fixture.button('Load photos').props.onClick();await flush();
        assert.equal(fixture.calls.length,2);assert.ok(fixture.field('salon-gallery-file'));assert.doesNotMatch(fixture.text(),/couldn’t load|temporarily unavailable/);
    } finally {fixture.app.unmount();}
});


test('multiple selection uploads in order with one action and each confirmed revision', async () => {
    const fixture=mount(true,{server:{photos:[],revision:'empty',limit:8}});
    try {
        await flush();assert.equal(fixture.field('salon-gallery-file').props.multiple,'');
        fixture.choose([{name:'first.png'},{name:'second.png'},{name:'third.png'}]);await flush();
        assert.equal(fixture.calls.length,1);assert.equal(fixture.previews.length,3);assert.match(fixture.text(),/Add 3 photos/);
        fixture.submit();fixture.submit();await flush();
        assert.equal(fixture.calls.filter(call=>call.method==='post').length,1);assert.match(fixture.text(),/Adding photo 1 of 3/);
        const keys=[];
        for(let index=0;index<3;index++) {
            const call=fixture.calls.at(-1);keys.push(call.payload.values.get('idempotency_key'));
            assert.equal(call.payload.values.get('photo').name,['first.png','second.png','third.png'][index]);
            assert.equal(call.payload.values.get('revision'),index ? `saved-${index}` : 'empty');
            call.resolve({data:{revision:`saved-${index+1}`,limit:8,photos:Array.from({length:index+1},(_,i)=>({public_id:`saved-${i}`,caption:'',url:`/saved-${i}`}))}});await flush();
        }
        assert.equal(new Set(keys).size,3);assert.match(fixture.text(),/3 photos added/);assert.doesNotMatch(fixture.text(),/Not added yet/);
        assert.deepEqual(fixture.revoked,['blob:preview-1','blob:preview-2','blob:preview-3']);
        assert.equal(fixture.events.at(-1)[1],false);
    } finally {fixture.app.unmount();}
});

test('partial failure stops later uploads and retries the exact failed photo without repeating successful ones', async () => {
    const fixture=mount();
    try {
        await flush();fixture.choose([{name:'first.png'},{name:'second.png'},{name:'third.png'}]);await flush();
        const toggles=fixture.nodes().filter(node=>node.type==='button'&&node.props['aria-controls']?.startsWith('selected-caption-'));
        toggles[1].props.onClick();await flush();
        const caption=fixture.nodes().find(node=>node.props?.id?.startsWith('gallery-new-caption-'));
        caption.props['onUpdate:modelValue']('Styling area');
        fixture.submit();fixture.calls.at(-1).resolve({data:gallery('after-first')});await flush();
        const failed=fixture.calls.at(-1);assert.equal(failed.payload.values.get('caption'),'Styling area');failed.reject({});await flush();
        assert.match(fixture.text(),/1 photo added/);assert.match(fixture.text(),/2 photos selected/);
        assert.equal(fixture.calls.filter(call=>call.method==='post').length,2);assert.equal(caption.props.disabled,true);
        fixture.submit();const retry=fixture.calls.at(-1);assert.equal(retry.payload.values.get('idempotency_key'),failed.payload.values.get('idempotency_key'));
        assert.equal(retry.payload.values.get('caption'),'Styling area');assert.equal(retry.payload.values.get('photo').name,'second.png');
        retry.resolve({data:gallery('after-second')});await flush();assert.equal(fixture.calls.at(-1).payload.values.get('photo').name,'third.png');
        assert.equal(fixture.calls.at(-1).payload.values.get('revision'),'after-second');fixture.calls.at(-1).resolve({data:gallery('after-third')});await flush();
        assert.equal(fixture.calls.filter(call=>call.method==='post').length,4);
    } finally {fixture.app.unmount();}
});

test('mixed selections preserve valid photos, reject duplicates/invalid files and never exceed remaining spaces', async () => {
    const fixture=mount(true,{server:{...gallery('start'),limit:4}});
    try {
        await flush();fixture.choose([{name:'first.png'},{name:'first.png'},{name:'notes.svg',type:'image/svg+xml'},{name:'huge.jpg',type:'image/jpeg',size:6*1024*1024},{name:'second.png'},{name:'extra.png'}]);await flush();
        assert.equal(fixture.previews.length,2);assert.match(fixture.text(),/Some photos weren’t selected/);assert.match(fixture.text(),/already selected/);
        assert.match(fixture.text(),/2 photos selected/);assert.match(fixture.text(),/0 spaces available/);assert.equal(fixture.field('salon-gallery-file'),undefined);
        assert.equal(fixture.calls.filter(call=>call.method==='post').length,0);
        fixture.nodes().find(node=>node.props?.['aria-label']==='Remove first.png from selection').props.onClick();await flush();
        assert.ok(fixture.field('salon-gallery-file'));assert.match(fixture.text(),/1 space available/);
    } finally {fixture.app.unmount();}
});

test('a stale upload cannot continue until reload and preserves the whole remaining queue', async () => {
    const fixture=mount();
    try {
        await flush();fixture.choose([{name:'first.png'},{name:'second.png'}]);fixture.submit();
        fixture.calls.at(-1).reject({response:{status:422,data:{errors:{revision:['Your gallery changed in another window. Reload the photos before saving.']}}}});await flush();
        fixture.choose({name:'third.png'});await flush();assert.ok(fixture.button('Reload photos'));
        fixture.submit();assert.equal(fixture.calls.filter(call=>call.method==='post').length,1);
        fixture.server(gallery('reloaded'));fixture.button('Reload photos').props.onClick();await flush();
        assert.equal(fixture.previews.length,3);fixture.submit();const retry=fixture.calls.at(-1);assert.equal(retry.payload.values.get('revision'),'reloaded');
        retry.resolve({data:gallery('first-confirmed')});await flush();fixture.calls.at(-1).resolve({data:gallery('second-confirmed')});await flush();fixture.calls.at(-1).resolve({data:gallery('third-confirmed')});await flush();
    } finally {fixture.app.unmount();}
});

test('account restrictions prevent picker and dropped files from creating an upload queue', async () => {
    const fixture=mount();
    try {
        await flush();fixture.state.disabled=true;await flush();assert.equal(fixture.field('salon-gallery-file').props.disabled,true);
        fixture.choose([{name:'one.png'},{name:'two.png'}]);await flush();
        const drop=fixture.nodes().find(node=>node.props?.onDrop);drop.props.onDrop({preventDefault(){},dataTransfer:{files:[{name:'dropped.png',type:'image/png',size:1024}]}});await flush();
        assert.equal(fixture.previews.length,0);assert.equal(fixture.calls.filter(call=>call.method==='post').length,0);
    } finally {fixture.app.unmount();}
});


test('leaving during a batch never starts another upload and releases remaining previews', async () => {
    const fixture=mount();await flush();fixture.choose([{name:'one.png'},{name:'two.png'}]);fixture.submit();
    const inFlight=fixture.calls.at(-1);fixture.app.unmount();inFlight.resolve({data:gallery('after-leave')});await flush();
    assert.equal(fixture.calls.filter(call=>call.method==='post').length,1);assert.deepEqual(fixture.revoked,['blob:preview-1','blob:preview-2']);
});


test('an invalid image requires removal instead of offering the identical failed upload again', async () => {
    const fixture=mount();
    try {
        await flush();fixture.choose([{name:'too-small.png'},{name:'good.png'}]);fixture.submit();
        fixture.calls.at(-1).reject({response:{status:422,data:{errors:{photo:['Choose a photo at least 160 pixels on each side.']}}}});await flush();
        assert.match(fixture.text(),/too-small.png/);assert.ok(fixture.button('Remove this photo'));
        fixture.choose({name:'another-good.png'});await flush();assert.ok(fixture.button('Remove this photo'));
        fixture.nodes().find(node=>node.props?.['aria-label']==='Remove another-good.png from selection').props.onClick();await flush();assert.ok(fixture.button('Remove this photo'));
        fixture.submit();assert.equal(fixture.calls.filter(call=>call.method==='post').length,1);
        fixture.button('Remove this photo').props.onClick();await flush();fixture.submit();
        assert.equal(fixture.calls.at(-1).payload.values.get('photo').name,'good.png');fixture.calls.at(-1).resolve({data:gallery('good')});await flush();
    } finally {fixture.app.unmount();}
});
