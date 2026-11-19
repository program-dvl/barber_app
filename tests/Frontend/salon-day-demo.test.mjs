import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
import {parse,compileScript} from '@vue/compiler-sfc';
import {createRenderer,h,nextTick,toRaw} from 'vue';
const {descriptor}=parse(await readFile('resources/js/Components/Marketing/SalonDayDemo.vue','utf8'));
const vue=pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const code=compileScript(descriptor,{id:'demo',inlineTemplate:true}).content
 .replace(/from ['"]vue['"]/g,`from '${vue}'`)
 .replace(/import PublicContainer from '[^']+';/,`const PublicContainer={inheritAttrs:false,setup(p,{slots}){return()=>slots.default?.()}};`)
 .replace(/import AppNotice from '[^']+';/,`const AppNotice={props:['title','description','tone'],setup(p){return()=>p.title+' '+p.description}};`)
 .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/,(_,names)=>names.split(',').map(n=>`const ${n.trim()}={render(){return null}};`).join('\n'));
const Demo=(await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
function mount(){
 const root={children:[]}; let focused;
 const renderer=createRenderer({createElement:type=>({type,props:{},children:[],focus(){focused=this}}),createText:text=>({text}),createComment:()=>({text:''}),insert(c,p,a){if(c.parent)c.parent.children=c.parent.children.filter(n=>n!==c);c.parent=p;let i=p.children.indexOf(a);if(i<0)p.children.push(c);else p.children.splice(i,0,c)},remove(n){n.parent.children=n.parent.children.filter(c=>c!==n)},setText:(n,t)=>n.text=t,setElementText:(n,t)=>{n.text=t;n.children=[]},parentNode:n=>n.parent,nextSibling:n=>n.parent?.children[n.parent.children.indexOf(n)+1],patchProp:(n,k,b,a)=>n.props[k]=a});
 const app=renderer.createApp({render:()=>h(Demo)});app.mount(root);
 const nodes=()=>{const all=[];const walk=n=>{all.push(n);n.children?.forEach(walk)};walk(root);return all};
 const text=n=>[n.text||'',...(n.children||[]).map(text)].join(' ');
 const button=label=>nodes().find(n=>n.type==='button'&&(n.props['aria-label']===label||text(n).trim()===label));
 const click=async n=>{assert.ok(n);assert.ok(!n.props.disabled);n.props.onClick();await nextTick();await nextTick()};
 return {app,nodes,button,click,text:()=>text(root),focus:()=>focused};
}
test('sample walk-in requires a choice, cannot double-book, and replay restores both choices and focus',async()=>{
 const ui=mount();
 const alex='Choose Alex at 10:30 for Jamie’s 30-minute haircut';
 const taylor='Choose Taylor at 11:00 for Jamie’s 30-minute haircut';
 assert.equal(ui.button(alex).props.disabled,true);
 assert.doesNotMatch(ui.text(),/no real booking was made/);
 await ui.click(ui.button('Find a time'));
 assert.equal(toRaw(ui.focus()),ui.button(alex));
 assert.equal(ui.button(taylor).props.disabled,false);
 assert.equal(ui.nodes().filter(n=>n.props?.class?.includes('salon-booked')).length,10);
 await ui.click(ui.button(alex));
 assert.match(ui.text(),/Alex at 10:30/);assert.match(ui.text(),/no real booking was made/);
 assert.equal(ui.button(taylor).props.disabled,true);
 ui.button(taylor).props.onClick();await nextTick();
 assert.match(ui.text(),/haircut with Alex at 10:30/);
 await ui.click(ui.button('Replay'));
 assert.equal(toRaw(ui.focus()),ui.button('Find a time'));assert.equal(ui.button(alex).props.disabled,true);
 await ui.click(ui.button('Find a time'));await ui.click(ui.button(taylor));
 assert.match(ui.text(),/haircut with Taylor at 11:00/);
 ui.app.unmount();
});
