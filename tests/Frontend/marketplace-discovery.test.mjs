import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick, h } from 'vue';
const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const blank = { render() { return null; } };
const container = { setup(props, { slots }) { return () => h('div', slots.default?.()); } };
const notice = { props: ['title', 'description'], setup(props, { slots }) { return () => h('div', [props.title, props.description, slots.actions?.()]); } };
globalThis.marketplaceTest = {};
async function load(path) {
    const { descriptor } = parse(await readFile(resolve(path), 'utf8'));
    const source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replace(/from (['"])vue\1/g, `from '${vue}'`)
        .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/g, (_, names) => names.split(',').map(name => `const ${name.trim()} = {render(){return null}};`).join('\n'))
        .replace(/import \{[^}]+\} from '@inertiajs\/vue3';/g, match => match.includes('useForm') ? 'const useForm = data => ({...data,delete(){},post(){}});' : 'const Head = globalThis.marketplaceTest.container, Link = globalThis.marketplaceTest.container, router = {get:(...args)=>globalThis.marketplaceTest.get(...args)};')
        .replace("import axios from 'axios';", 'const axios = {get:(...args)=>globalThis.marketplaceTest.services(...args)};')
        .replace(/import \{[^}]+\} from '@\/Support\/googleMaps';/, 'const loadGoogleMaps = () => globalThis.marketplaceTest.maps(); const mapId = () => "test-map"; const onGoogleMapsFailure = callback => {globalThis.marketplaceTest.failure=callback;return ()=>{};};')
        .replace(/import (\w+) from '(?:@\/|\.\/)[^']+';/g, (_, name) => `const ${name} = globalThis.marketplaceTest.components.${name} || {render(){return null}};`);
    return (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
}
globalThis.marketplaceTest.container = container;
globalThis.marketplaceTest.components = { MarketplaceLayout: container, AppNotice: notice };
const Discover = await load('resources/js/Pages/Marketplace/Discover.vue');
const Search = await load('resources/js/Components/Marketplace/DiscoverySearch.vue');
const MapView = await load('resources/js/Components/Locations/MarketplaceMap.vue');
const LocationSearch = await load('resources/js/Components/Marketplace/LocationSearch.vue');
const flush = async () => { for (let i = 0; i < 20; i++) await nextTick(); };
function mount(Component, props) {
    globalThis.window = {matchMedia:()=>({matches:false})};
    globalThis.document = { addEventListener() {}, removeEventListener() {}, getElementById: () => ({ scrollIntoView: () => globalThis.marketplaceTest.scrolled = true }), createElement: () => ({ style: {} }) };
    const root = { children: [] };
    const renderer = createRenderer({
        createElement(type) { return { type, tagName: type.toUpperCase(), props: {}, children: [], listeners: {}, style: {}, get options() { return this.children; }, value: '', addEventListener(name, fn) { this.listeners[name] = fn; }, removeEventListener() {}, setAttribute(name, value) { this.props[name] = value; }, removeAttribute(name) { delete this.props[name]; }, replaceChildren(...children) { this.children = children; }, contains() { return true; }, querySelector() { return null; } }; },
        createText: text => ({ text }), createComment: () => ({ text: '' }),
        insert(child, parent, anchor) { if (child.parent) child.parent.children = child.parent.children.filter(node => node !== child); child.parent = parent; const index = parent.children.indexOf(anchor); if (index < 0) parent.children.push(child); else parent.children.splice(index, 0, child); },
        remove(node) { if (node.parent) node.parent.children = node.parent.children.filter(child => child !== node); },
        setText: (node, text) => node.text = text, setElementText: (node, text) => { node.text = text; node.children = []; },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1],
        patchProp(node, key, before, after) { node.props[key] = after; if (key === 'value') node.value = after; },
    });
    const app = renderer.createApp({ render: () => h(Component, props) }); app.mount(root);
    const nodes = () => { const all = []; function walk(node) { all.push(node); node.children?.forEach(walk); } walk(root); return all; };
    const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ').replace(/\s+/g, ' ').trim();
    const button = label => nodes().find(node => node.tagName === 'BUTTON' && text(node) === label);
    const click = async node => { assert.ok(node, 'Expected button exists'); await node.props.onClick?.({ preventDefault() {}, stopPropagation() {} }); await flush(); };
    return { app, nodes, button, click, text: () => text(root) };
}
test('category cards submit the correct category and reveal results after the response', async () => {
    const calls = []; globalThis.marketplaceTest.get = (url, query, options) => { calls.push({url,query}); options.onSuccess(); options.onFinish(); };
    globalThis.marketplaceTest.scrolled = false;
    const f = mount(Discover, { filters: { q: '', location: '' }, results: { total: 0, data: [] } });
    try {
        await f.click(f.button('Nails The finishing touch.'));
        assert.equal(calls[0].query.category, 'nails'); assert.equal(calls[0].query.q, ''); assert.equal(globalThis.marketplaceTest.scrolled, true);
        assert.equal(f.button('Nails ✓ The finishing touch.')?.props['aria-pressed'] ?? f.button('Nails The finishing touch.').props['aria-pressed'], true);
        await f.click(f.button('Hair A little reinvention.')); assert.equal(calls[1].query.category, 'hair');
    } finally { f.app.unmount(); }
});
test('service search offers actual catalogue services and emits the selected specific service', async () => {
    globalThis.marketplaceTest.services = async () => ({ data: { services: [{ name: 'Balayage colour', category: 'Colour' }, { name: 'Gel manicure', category: 'Nails' }] } });
    const selected = []; const f = mount(Search, { query: '', location: '', date: '', onService: value => selected.push(value) });
    try {
        await f.nodes().find(node => node.props?.name === 'q').props.onFocus(); await flush();
        assert.match(f.text(), /Balayage colour/); assert.match(f.text(), /Gel manicure/);
        assert.ok(!f.button('Hair')); await f.click(f.button('Gel manicure Nails')); assert.deepEqual(selected, ['Gel manicure']);
    } finally { f.app.unmount(); }
});
function mapFixture() {
    const markers = [], moves = [];
    class Map { constructor(canvas, options) { this.zoom = options.zoom; } panTo(point) { moves.push(point); } getZoom() { return this.zoom; } setZoom(zoom) { this.zoom = zoom; } setCenter() {} fitBounds() {} }
    class Marker { constructor(options) { Object.assign(this, options); markers.push(this); } addListener(name, callback) { this.click = callback; } }
    globalThis.marketplaceTest.maps = async () => ({ importLibrary: async name => name === 'maps' ? { Map } : { AdvancedMarkerElement: Marker }, LatLngBounds: class { extend() {} }, event: { addListenerOnce() {} } });
    return {markers,moves};
}
const salons = [{ name: 'Juniper', url: '/juniper', address: 'Toronto', point: {lat:43.65,lng:-79.38}, reviews: {count:0}, distance:null }, { name:'No pin studio',url:'/no-pin',address:'Toronto',point:null,reviews:{count:0},distance:null }];
test('map pin selection previews the matching card and keeps salons without pins accessible', async () => {
    const {markers,moves} = mapFixture(); const f = mount(MapView, {salons}); await flush();
    try {
        assert.equal(markers.length, 1); markers[0].click(); await flush();
        assert.deepEqual(moves[0], salons[0].point); assert.equal(f.nodes().find(node=>node.tagName==='ARTICLE').props['aria-current'], 'true');
        assert.match(f.text(), /No pin studio/); assert.equal(f.nodes().filter(node=>node.tagName==='A').length, 2);
    } finally { f.app.unmount(); }
});
test('map denial keeps the results and offers a working list action', async () => {
    globalThis.marketplaceTest.maps = async () => {throw Error('denied');}; let list = false;
    const f = mount(MapView, {salons,onShowList:()=>list=true}); await flush();
    try { assert.match(f.text(), /Your business results are still here/); assert.match(f.text(), /Juniper/); await f.click(f.button('Show business list')); assert.equal(list,true); }
    finally { f.app.unmount(); }
});

test('Google location selection requests only transient geometry and ignores late selections after leaving', async () => {
    let widget; const fields = [], selected = [];
    class Widget {
        constructor() { widget=this;this.style={};this.listeners={}; }
        setAttribute() {} addEventListener(name,fn) {this.listeners[name]=fn;} remove() {}
    }
    globalThis.marketplaceTest.maps=async()=>({importLibrary:async()=>({PlaceAutocompleteElement:Widget})});
    const f=mount(LocationSearch,{onSelect:point=>selected.push(point)}); await flush();
    const event={placePrediction:{toPlace:()=>({fetchFields:async options=>fields.push(options.fields),location:{lat:()=>43.65123456,lng:()=>-79.38123456}})}};
    await widget.listeners['gmp-select'](event); await flush();
    assert.deepEqual(fields,[['location']]); assert.deepEqual(selected,[{lat:43.65123456,lng:-79.38123456}]);
    f.app.unmount(); await widget.listeners['gmp-select'](event); assert.equal(selected.length,1);
});

test('listing ratings show the actual score and count without the conflicting star-widget class or visit label',()=>{
    const f=mount(Discover,{filters:{q:'',location:'',rating:'',date:'',category:''},results:{data:[{name:'Rock Studio',url:'/marketplace/salons/rock',address:'601 Upper James Street',reviews:{count:1,average:4},distance:null}],total:1}});
    try{
        const rating=f.nodes().find(node=>node.props?.class==='salon-rating');
        assert.ok(rating);assert.equal(rating.props['aria-label'],'Rated 4.0 out of 5 from 1 review');
        assert.match(f.text(),/4\.0 \(1\)/);assert.doesNotMatch(f.text(),/verified visits/);
        assert.ok(!f.nodes().some(node=>node.props?.class==='rating'));
        assert.match(f.text(),/601 Upper James Street/);
    }finally{f.app.unmount();}
});
