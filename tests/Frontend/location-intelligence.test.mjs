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
 const app=renderer.createApp(Component,{modelValue:model,'onUpdate:modelValue':value=>events.push(value),...overrides});app.mount({children:[]});
 return {app,nodes,events};
}
async function flush(){for(let i=0;i<12;i++)await nextTick()}
globalThis.Document = class Document {};
globalThis.ShadowRoot = class ShadowRoot {};
const Component=await editorComponent();

test('actual editor makes one minimal selected details request, does not persist Google geometry, and saves an intentionally placed pin',async()=>{
 let widget;let map;let marker;let calls=0;let mask;
 class Widget {constructor(){widget=this;this.handlers={};this.style={}} setAttribute(){} addEventListener(name,handler){this.handlers[name]=handler}remove(){}}
 class Map {constructor(){map=this;this.handlers={}}addListener(name,handler){this.handlers[name]=handler}setCenter(){}setZoom(){}}
 class Marker {constructor(options){marker=this;Object.assign(this,options);this.handlers={}}addListener(name,handler){this.handlers[name]=handler}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async name=>name==='places'?{PlaceAutocompleteElement:Widget}:name==='maps'?{Map}:{AdvancedMarkerElement:Marker}}));await flush();
 await widget.handlers['gmp-select']({placePrediction:{toPlace:()=>({id:'Chosen',formattedAddress:'18 New Lane',addressComponents:[],location:{lat:()=>43.65,lng:()=>-79.38},fetchFields:async({fields})=>{calls++;mask=fields}})}});await flush();
 assert.equal(calls,1);assert.deepEqual(mask,['id','formattedAddress','addressComponents','location']);assert.equal(mounted.events[0].address,'18 New Lane');assert.equal(mounted.events[0].entrance_latitude,'');
 map.handlers.click({latLng:{lat:()=>43.651,lng:()=>-79.381}});assert.equal(mounted.events.at(-1).entrance_latitude,43.651);
 marker.position={lat:43.652,lng:-79.382};marker.handlers.dragend();assert.equal(mounted.events.at(-1).entrance_longitude,-79.382);mounted.app.unmount();
});
test('actual editor retains manual fallback after API quota exhaustion or network failure',async()=>{
 let widget;class Widget{constructor(){widget=this;this.style={};this.handlers={}}setAttribute(){}addEventListener(n,h){this.handlers[n]=h}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async()=>({PlaceAutocompleteElement:Widget})}));await flush();
 await widget.handlers['gmp-select']({placePrediction:{toPlace:()=>({fetchFields:async()=>{throw Error('RESOURCE_EXHAUSTED')}})}});await flush();
 const textarea=mounted.nodes.find(node=>node.type==='textarea'&&node.props.id==='location-address');textarea.props.onInput({target:{value:'Manual Market Road'}});
 assert.equal(mounted.events.at(-1).address,'Manual Market Road');assert.equal(mounted.events.at(-1).google_place_id,'');mounted.app.unmount();
 const failed=mountEditor(Component,async()=>{throw Error('Network unavailable')});await flush();assert.ok(failed.nodes.some(node=>node.type==='textarea'));failed.app.unmount();
});
test('stale place responses cannot overwrite a newer manual address',async()=>{
 let widget;let release;class Widget{constructor(){widget=this;this.style={};this.handlers={}}setAttribute(){}addEventListener(n,h){this.handlers[n]=h}remove(){}}
 const mounted=mountEditor(Component,async()=>({importLibrary:async()=>({PlaceAutocompleteElement:Widget})}));await flush();
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
