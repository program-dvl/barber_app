import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, nextTick } from 'vue';
import { placeAddress, coordinates, distanceKm, discoverBranches, navigationLinks, locationDefaults, retainBranchServices } from '../../resources/js/Support/locationIntelligence.js';

test('selected addresses use structured components including postal towns and incomplete regional addresses',()=>{
 const address=placeAddress({id:'Place-test', formattedAddress:'18 Example Rd, UK', addressComponents:[{types:['street_number'],longText:'18'},{types:['route'],longText:'Example Rd'},{types:['postal_town'],longText:'London'},{types:['country'],shortText:'GB'}], location:{lat:()=>51,lng:()=>0}});
 assert.equal(address.address_line_1,'18 Example Rd');assert.equal(address.city,'London');assert.equal(address.country_code,'GB');assert.equal(address.postal_code,'');assert.equal(address.entrance_latitude,'');assert.ok(!('latitude' in address));
 assert.equal(placeAddress({id:'partial'}).address,'');
});
test('navigation prioritizes owner entrance including zeros and encodes address/place fallbacks',()=>{
 const pin=navigationLinks({address:'Other address',google_place_id:'Ignored',entrance_latitude:0,entrance_longitude:0});
 assert.equal(new URL(pin.directions_url).searchParams.get('destination'),'0,0');assert.equal(new URL(pin.directions_url).searchParams.has('destination_place_id'),false);assert.ok(pin.apple_maps_url);
 const place=navigationLinks({address:'A & B Street',google_place_id:'Chosen'});assert.equal(new URL(place.directions_url).searchParams.get('destination_place_id'),'Chosen');assert.equal(place.apple_maps_url,null);
 assert.equal(coordinates({entrance_latitude:91,entrance_longitude:0}),null);assert.equal(coordinates({entrance_latitude:'',entrance_longitude:0}),null);
});
test('branch discovery filters city postcode name and orders known entrances before missing coordinates without mutating data',()=>{
 const branches=[{public_id:'far',name:'Far',city:'Toronto',entrance_latitude:44,entrance_longitude:-79},{public_id:'missing',name:'Main',postal_code:'M5V'},{public_id:'near',name:'Near',city:'Toronto',entrance_latitude:43.65,entrance_longitude:-79.38}];
 assert.deepEqual(discoverBranches(branches,'',{lat:43.65,lng:-79.38}).map(b=>b.public_id),['near','far','missing']);assert.equal(discoverBranches(branches,'m5v')[0].public_id,'missing');assert.equal(discoverBranches(branches,'Toronto').length,2);assert.equal(distanceKm({lat:0,lng:0},{lat:0,lng:0}),0);assert.ok(!('distance' in branches[0]));
});

// Compile the actual editor and mount it with Vue's host renderer; Google is mocked.
async function editorComponent(path = 'resources/js/Components/Locations/LocationEditor.vue') {
 const source=await readFile(resolve(path),'utf8');
 const {descriptor}=parse(source);
 let code=compileScript(descriptor,{id:'location-test',inlineTemplate:true}).content;
 code=code.replaceAll('from "vue"',`from ${JSON.stringify(pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href)}`).replaceAll("from 'vue'",`from ${JSON.stringify(pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href)}`);
 code=code.replace(/from '@\/Support\/locationIntelligence';/g,`from ${JSON.stringify(pathToFileURL(resolve('resources/js/Support/locationIntelligence.js')).href)};`);
 code=code.replace(/import \{ loadGoogleMaps, mapId, onGoogleMapsFailure \} from '[^']+';/,'const loadGoogleMaps=()=>globalThis.locationMapsMock(); const mapId=()=>"TEST_MAP_ID"; const onGoogleMapsFailure=callback=>{globalThis.locationFailureCallback=callback;return ()=>{}};');
 code=code.replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/,(_,names)=>names.split(',').map(name=>`const ${name.trim()}={render(){return null}};`).join('\n'));
 code=code.replace(/import (AppButton|AppSelect|FieldError) from '[^']+';/g,'const $1={render(){return testH("button",this.$attrs,this.$slots.default?.())}};');
 code=code.replace(/import AppNotice from '[^']+';/g,'const AppNotice=globalThis.locationNotice;');
 code=`import {h as testH} from ${JSON.stringify(pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href)};\n`+code;
 return (await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
}
function mountEditor(Component, mapsMock, overrides = {}) {
 globalThis.locationMapsMock=mapsMock;
 const nodes=[]; const events=[];
 const renderer=createRenderer({
  createElement:type=>{const el={type,children:[],props:{},style:{},getRootNode(){return {activeElement:null}},addEventListener(){},removeEventListener(){},replaceChildren(...children){this.children=children}};nodes.push(el);return el},
  insert:(child,parent)=>{child.parent=parent;parent.children.push(child)},remove:node=>{if(node.parent)node.parent.children=node.parent.children.filter(item=>item!==node)},createText:text=>({text}),createComment:text=>({text}),setText:(node,text)=>node.text=text,setElementText:(node,text)=>node.text=text, parentNode:node=>node.parent,nextSibling:()=>null,patchProp:(el,key,previous,next)=>el.props[key]=next,
 });
 const model={...locationDefaults(),address:'Existing address',name:'Studio'};
 const root={children:[]};
 const app=renderer.createApp(Component,{modelValue:model,'onUpdate:modelValue':value=>events.push(value),...overrides});app.mount(root);
 const text=(node,visibleOnly=false)=>visibleOnly && node.style?.display==='none'?'':[node.text || '',...(node.children || []).map(child=>text(child,visibleOnly))].join(' ').replace(/\s+/g,' ');
 return {app,nodes,events,text:()=>text(root),visibleText:()=>text(root,true)};
}
async function flush(){for(let i=0;i<12;i++)await nextTick()}
globalThis.Document = class Document {};
globalThis.ShadowRoot = class ShadowRoot {};
globalThis.document={getElementById(){return null}};
globalThis.locationNotice=await editorComponent('resources/js/Components/Product/AppNotice.vue');
const Component=await editorComponent();

test('actual editor makes one minimal selected details request, does not persist Google geometry, and saves an intentionally placed pin',async()=>{
 let widget;let map;let marker;let calls=0;let mask;
 class Widget {constructor(){widget=this;this.handlers={};this.style={}} setAttribute(){} addEventListener(name,handler){this.handlers[name]=handler}remove(){}}
 class Map {constructor(){map=this;this.handlers={}}addListener(name,handler){this.handlers[name]=handler}setCenter(){}setZoom(){}}
 class Marker {constructor(options){marker=this;Object.assign(this,options);this.handlers={}}addListener(name,handler){this.handlers[name]=handler}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async name=>name==='places'?{PlaceAutocompleteElement:Widget}:name==='maps'?{Map}:{AdvancedMarkerElement:Marker}}),{modelValue:{...locationDefaults(),address:'Existing address',google_place_id:'Existing'}});await flush();
 assert.equal(widget.style.colorScheme,'light');assert.equal(widget.style.backgroundColor,'var(--surface-raised)');assert.equal(widget.style.color,'var(--text-default)');
 await widget.handlers['gmp-select']({placePrediction:{toPlace:()=>({id:'Chosen',formattedAddress:'18 New Lane',addressComponents:[],location:{lat:()=>43.65,lng:()=>-79.38},fetchFields:async({fields})=>{calls++;mask=fields}})}});await flush();
 assert.equal(calls,1);assert.deepEqual(mask,['id','formattedAddress','addressComponents','location']);assert.equal(mounted.events[0].address,'18 New Lane');assert.equal(mounted.events[0].entrance_latitude,'');
 assert.equal(map, undefined);
 await mounted.nodes.find(node=>node.type==='button'&&node.props.onClick&&node.children.some(child=>child.text?.includes('entrance pin'))).props.onClick();await flush();
 map.handlers.click({latLng:{lat:()=>43.651,lng:()=>-79.381}});assert.equal(mounted.events.at(-1).entrance_latitude,43.651);
 marker.position={lat:43.652,lng:-79.382};marker.handlers.dragend();assert.equal(mounted.events.at(-1).entrance_longitude,-79.382);mounted.app.unmount();
});
test('actual editor retains manual fallback after API quota exhaustion or network failure',async()=>{
 let widget;class Widget{constructor(){widget=this;this.style={};this.handlers={}}setAttribute(){}addEventListener(n,h){this.handlers[n]=h}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async()=>({PlaceAutocompleteElement:Widget})}),{modelValue:{...locationDefaults(),address:'Existing address',google_place_id:'Existing'}});await flush();
 await widget.handlers['gmp-select']({placePrediction:{toPlace:()=>({fetchFields:async()=>{throw Error('RESOURCE_EXHAUSTED')}})}});await flush();
 const textarea=mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address');textarea.props.onInput({target:{value:'Manual Market Road'}});
 assert.equal(mounted.events.at(-1).address,'Manual Market Road');assert.equal(mounted.events.at(-1).google_place_id,'');mounted.app.unmount();
 const failed=mountEditor(Component,async()=>{throw Error('Network unavailable')});await flush();assert.ok(failed.nodes.some(node=>node.type==='textarea'));failed.app.unmount();
});
test('denied search keeps its query for manual entry and stops unusable searches without guessing quota',async()=>{
 let widget;let detailsRequests=0;
 class Widget{constructor(){widget=this;this.style={};this.handlers={};this.attributes={};this.value='111 Example Street'}setAttribute(name,value){this.attributes[name]=value}addEventListener(name,handler){this.handlers[name]=handler}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async()=>({PlaceAutocompleteElement:Widget})}),{modelValue:{...locationDefaults(),address:'',name:'Kept studio'}});await flush();
 widget.handlers['gmp-error']();await flush();
 assert.equal(widget.attributes.disabled,'');assert.match(mounted.text(),/Address search is unavailable/);assert.match(mounted.text(),/Your other details are kept/);assert.doesNotMatch(mounted.text(),/quota|limit|incorrect API|credential/i);
 await widget.handlers['gmp-select']({placePrediction:{toPlace:()=>({fetchFields:async()=>{detailsRequests++}})}});assert.equal(detailsRequests,0);
 const manual=mounted.nodes.find(node=>node.type==='button'&&node.children.some(child=>child.text==='Enter address manually'));manual.props.onClick();await flush();
 assert.equal(mounted.events.at(-1).address,'111 Example Street');assert.equal(mounted.events.at(-1).name,'Kept studio');
 assert.notEqual(mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address').parent.parent.style.display,'none');mounted.app.unmount();
});
test('manual recovery preserves an existing address and never reveals raw load errors',async()=>{
 const failure=Object.assign(Error('Private provider diagnostic and key=secret'),{code:'MAPS_AUTHENTICATION_FAILED'});
 const mounted=mountEditor(Component,async()=>{throw failure},{modelValue:{...locationDefaults(),address:'Saved full address',google_place_id:'Existing',entrance_latitude:43.65,entrance_longitude:-79.38}});await flush();
 assert.doesNotMatch(mounted.text(),/Private provider|secret|API|limit/);assert.ok(!mounted.nodes.some(node=>node.type==='button'&&node.children.some(child=>child.text==='Try search again')));
 const manual=mounted.nodes.find(node=>node.type==='button'&&node.children.some(child=>child.text==='Enter address manually'));manual.props.onClick();await flush();
 assert.equal(mounted.events.length,0);assert.equal(mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address').props.value,'Saved full address');mounted.app.unmount();
});
test('authentication failure during a delayed Places import cannot recreate an enabled search',async()=>{
 let releasePlaces;let widgets=0;
 class Widget{constructor(){widgets++;this.style={}}setAttribute(){}addEventListener(){}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:()=>new Promise(resolve=>releasePlaces=resolve)}),{modelValue:{...locationDefaults(),address:'',name:'Kept studio'}});await flush();
 assert.equal(typeof releasePlaces,'function');
 globalThis.locationFailureCallback('MAPS_AUTHENTICATION_FAILED');await flush();
 assert.match(mounted.text(),/Address search is unavailable/);
 releasePlaces({PlaceAutocompleteElement:Widget});await flush();
 assert.equal(widgets,0);assert.match(mounted.text(),/Address search is unavailable/);
 assert.equal(mounted.nodes.find(node=>node.props.id==='location-search').props['aria-disabled'],true);
 assert.ok(!mounted.nodes.some(node=>node.type==='button'&&node.children.some(child=>child.text==='Try search again')));
 mounted.nodes.find(node=>node.type==='button'&&node.children.some(child=>child.text==='Enter address manually')).props.onClick();await flush();
 const address=mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address');address.props.onInput({target:{value:'Manual saved address'}});
 assert.equal(mounted.events.at(-1).address,'Manual saved address');assert.equal(mounted.events.at(-1).name,'Kept studio');mounted.app.unmount();
});
test('authentication failure during an entrance map import cannot construct a hidden denied map',async()=>{
 let releaseMap;let maps=0;
 class Widget{constructor(){this.style={}}setAttribute(){}addEventListener(){}remove(){}}
 class Map{constructor(){maps++}addListener(){}}
 class Marker{addListener(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async name=>name==='places'?{PlaceAutocompleteElement:Widget}:name==='maps'?new Promise(resolve=>releaseMap=resolve):{AdvancedMarkerElement:Marker}}));await flush();
 const opening=mounted.nodes.find(node=>node.type==='button'&&node.props.onClick&&node.children.some(child=>child.text?.includes('entrance pin'))).props.onClick();await flush();
 globalThis.locationFailureCallback('MAPS_AUTHENTICATION_FAILED');await flush();releaseMap({Map});await opening;await flush();
 assert.equal(maps,0);assert.match(mounted.text(),/The entrance map is unavailable/);assert.match(mounted.text(),/save this location without an entrance pin/);
 assert.equal(mounted.events.length,0);mounted.app.unmount();
});
test('a shared authentication failure shows one recovery notice for the selected address mode',async()=>{
 let releaseMap;
 class Widget{constructor(){this.style={}}setAttribute(){}addEventListener(){}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async name=>name==='places'?{PlaceAutocompleteElement:Widget}:name==='maps'?new Promise(resolve=>releaseMap=resolve):{AdvancedMarkerElement:class {}}}),{modelValue:{...locationDefaults(),address:'Saved address',google_place_id:'Saved-place'}});await flush();
 const opening=mounted.nodes.find(node=>node.type==='button'&&node.props.onClick&&node.children.some(child=>child.text?.includes('entrance pin'))).props.onClick();await flush();
 globalThis.locationFailureCallback('MAPS_AUTHENTICATION_FAILED');releaseMap({Map:class {}});await opening;await flush();
 assert.match(mounted.visibleText(),/Address search is unavailable/);assert.doesNotMatch(mounted.visibleText(),/The entrance map is unavailable/);
 mounted.nodes.find(node=>node.type==='button'&&node.children.some(child=>child.text==='Enter address manually')).props.onClick();await flush();
 assert.match(mounted.visibleText(),/The entrance map is unavailable/);assert.doesNotMatch(mounted.visibleText(),/Address search is unavailable/);
 assert.equal(mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address').props.value,'Saved address');assert.equal(mounted.events.length,0);
 mounted.nodes.find(node=>node.type==='input'&&node.props.value==='search').props.onChange();await flush();
 assert.match(mounted.visibleText(),/Address search is unavailable/);assert.doesNotMatch(mounted.visibleText(),/The entrance map is unavailable/);mounted.app.unmount();
});
test('stale place responses cannot overwrite a newer manual address',async()=>{
 let widget;let release;class Widget{constructor(){widget=this;this.style={};this.handlers={}}setAttribute(){}addEventListener(n,h){this.handlers[n]=h}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async()=>({PlaceAutocompleteElement:Widget})}),{modelValue:{...locationDefaults(),address:'Existing address',google_place_id:'Existing'}});await flush();
 const selected=widget.handlers['gmp-select']({placePrediction:{toPlace:()=>({id:'old',formattedAddress:'Old',fetchFields:()=>new Promise(resolve=>release=resolve)})}});
 const textarea=mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address');textarea.props.onInput({target:{value:'New manual address'}});release();await selected;
 assert.equal(mounted.events.length,1);assert.equal(mounted.events[0].address,'New manual address');mounted.app.unmount();
});

test('owner editor presents no coordinate inputs and keeps optional data entry closed',async()=>{
 const mounted=mountEditor(Component,async()=>{throw Error('offline')});await flush();
 assert.equal(mounted.nodes.filter(node=>node.type==='input'&&node.props.type==='number').length,0);
 assert.equal(mounted.nodes.filter(node=>node.type==='details').length,2);
 assert.ok(mounted.nodes.filter(node=>node.type==='details').every(node=>!node.props.open));mounted.app.unmount();
});

const Picker=await editorComponent('resources/js/Components/Locations/BranchPicker.vue');
test('actual branch picker switches inline, searches postal codes and never asks for geolocation on load',async()=>{
 let requests=0;const original=Object.getOwnPropertyDescriptor(globalThis,'navigator');
 Object.defineProperty(globalThis,'navigator',{configurable:true,value:{geolocation:{getCurrentPosition(success){requests++;success({coords:{latitude:0,longitude:0}})}}}});
 const branches=[{public_id:'a',name:'Alpha',postal_code:'A1',entrance_latitude:0,entrance_longitude:0},{public_id:'b',name:'Beta',postal_code:'B2',entrance_latitude:1,entrance_longitude:1}];
 const mounted=mountEditor(Picker,null,{branches,modelValue:'a'});await flush();assert.equal(requests,0);
 const radios=mounted.nodes.filter(node=>node.type==='input'&&node.props.type==='radio');assert.equal(radios.length,2);radios[1].props.onChange();assert.equal(mounted.events.at(-1),'b');
 const search=mounted.nodes.find(node=>node.type==='input'&&node.props.type==='search');search.props['onUpdate:modelValue']('b2');await flush();
 const activeRadios=mounted.nodes.filter(node=>node.parent&&node.parent.children.includes(node)&&node.type==='input'&&node.props.type==='radio');assert.ok(activeRadios.some(node=>node.props.value==='b'));
 const useLocation=mounted.nodes.find(node=>node.type==='button'&&node.props.onClick);useLocation.props.onClick();await flush();assert.equal(requests,1);mounted.app.unmount();
 if(original)Object.defineProperty(globalThis,'navigator',original);else delete globalThis.navigator;
});

test('branch switching preserves common services and only removes unavailable services or orphan add-ons',()=>{
 const catalog=[{public_id:'shared',kind:'service',addon_ids:['addon']},{public_id:'addon',kind:'addon'},{public_id:'second-only',kind:'service'}];
 assert.deepEqual(retainBranchServices(['shared','addon','first-only'],catalog),['shared','addon']);
 assert.deepEqual(retainBranchServices(['first-only','addon'],catalog),[]);
 assert.deepEqual(retainBranchServices(['shared'],catalog),['shared']);
});

const Card=await editorComponent('resources/js/Components/Locations/LocationCard.vue');
test('customer map loads only on request and reuses its map when reopened',async()=>{
 let mapLoads=0;class Map{constructor(){mapLoads++}setCenter(){}}class Marker{constructor(options){Object.assign(this,options)}};
 const mounted=mountEditor(Card,async()=>({importLibrary:async name=>name==='maps'?{Map}:{AdvancedMarkerElement:Marker}}),{location:{name:'Studio',address:'18 Example Lane',entrance_latitude:0,entrance_longitude:0}});await flush();assert.equal(mapLoads,0);
 const control=mounted.nodes.find(node=>node.type==='button'&&node.props['aria-expanded']===false);
 await control.props.onClick();await flush();assert.equal(mapLoads,1);assert.equal(control.props['aria-expanded'],true);
 await control.props.onClick();await flush();assert.equal(control.props['aria-expanded'],false);
 await control.props.onClick();await flush();assert.equal(mapLoads,1);mounted.app.unmount();
});

test('manual address corrections preserve owner entrance coordinates and clear stale derived address components',async()=>{
 const mounted=mountEditor(Component,async()=>{throw Error('offline')},{modelValue:{...locationDefaults(),address:'Old street, Old city',city:'Old city',postal_code:'OLD',entrance_latitude:43.65,entrance_longitude:-79.38}});await flush();
 const textarea=mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address');textarea.props.onInput({target:{value:'Corrected street, New city'}});
 assert.equal(mounted.events.at(-1).entrance_latitude,43.65);assert.equal(mounted.events.at(-1).city,'');assert.equal(mounted.events.at(-1).postal_code,'');mounted.app.unmount();
});
