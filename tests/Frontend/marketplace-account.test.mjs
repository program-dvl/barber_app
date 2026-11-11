import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createRenderer, h, nextTick, reactive } from 'vue';
import { openAcquisitionPreferences, ACQUISITION_CONSENT_KEY } from '../../resources/js/Support/marketplaceAttribution.js';
const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const container = {setup(props,{slots}){return()=>h('div',[slots.actions?.(),slots.default?.()]);}};
const Link = {props:['href'],setup(props,{attrs,slots}){return()=>h('a',{...attrs,href:props.href},slots.default?.());}};
const PhoneInput={props:['modelValue'],emits:['update:modelValue'],setup(props,{attrs,emit}){return()=>h('input',{...attrs,value:props.modelValue,onInput:event=>emit('update:modelValue',event.target.value)});}};
globalThis.accountUiTest={components:{MarketplaceAccountLayout:container,MarketplaceLayout:container,MarketplaceRecovery:container,AppLayout:container,PhoneInput},Link,container};
async function load(path){
    const {descriptor}=parse(await readFile(resolve(path),'utf8'));
    const source=compileScript(descriptor,{id:path,inlineTemplate:true}).content
        .replace(/from (['"])vue\1/g,`from '${vue}'`)
        .replace(/import \{ ([^}]+) \} from '@heroicons\/vue\/24\/outline';/g,(_,names)=>names.split(',').map(name=>`const ${name.trim()}={render(){return null}};`).join('\n'))
        .replace(/import \{([^}]+)\} from '@inertiajs\/vue3';/g,(_,names)=>names.split(',').map(name=>{name=name.trim();return `const ${name} = ${name==='useForm'?'(...args)=>globalThis.accountUiTest.form(...args)':name==='usePage'?'()=>globalThis.accountUiTest.page':name==='router'?' {reload:(...args)=>globalThis.accountUiTest.reload(...args),get:(...args)=>globalThis.accountUiTest.get(...args),on:()=>()=>{}}':name==='Link'?'globalThis.accountUiTest.Link':'globalThis.accountUiTest.container'};`;}).join('\n'))
        .replace("import axios from 'axios';", 'const axios={post:(...args)=>globalThis.accountUiTest.axios.post(...args),delete:(...args)=>globalThis.accountUiTest.axios.delete(...args)};')
        .replace(/import (\w+) from '(?:@\/|\.\/)[^']+';/g,(_,name)=>`const ${name}=globalThis.accountUiTest.components.${name} || {render(){return null}};`)
        .replace(/import '[^']+\.css';/g,'')
        .replace("from '@/Support/marketplaceAttribution';",`from '${pathToFileURL(resolve('resources/js/Support/marketplaceAttribution.js')).href}';`);
    return(await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
}
globalThis.accountUiTest.components.AppButton=await load('resources/js/Components/Product/AppButton.vue');
globalThis.accountUiTest.components.AppNotice=await load('resources/js/Components/Product/AppNotice.vue');
globalThis.accountUiTest.components.AppDialog=await load('resources/js/Components/Product/AppDialog.vue');
globalThis.accountUiTest.components.PageHeader=container;
globalThis.accountUiTest.components.BusinessMessagesLayout=await load('resources/js/Layouts/BusinessMessagesLayout.vue');
const BusinessListing=await load('resources/js/Pages/Marketplace/Business.vue');
const BusinessInbox=await load('resources/js/Pages/Marketplace/BusinessInbox.vue');
const Account=await load('resources/js/Pages/Marketplace/Account.vue');
const Inbox=await load('resources/js/Pages/Marketplace/Inbox.vue');
const Profile=await load('resources/js/Pages/Marketplace/Profile.vue');
const Conversation=await load('resources/js/Pages/Marketplace/Conversation.vue');
const AcquisitionConsent=await load('resources/js/Components/Marketplace/AcquisitionConsent.vue');
const Recovery=await load('resources/js/Components/Product/MarketplaceRecovery.vue');
const Shell=await load('resources/js/Layouts/MarketplaceAccountLayout.vue');
const flush=async()=>{for(let i=0;i<20;i++)await nextTick();};
function mount(Component,props,shared={}){
    globalThis.accountUiTest.page={url:'/marketplace/account',props:{marketplaceCustomer:{name:'Jordan Preview',email:'customer@example.test'},brand:{support_email:'support@example.test'},...shared}};
    const forms=[],calls=[];
    globalThis.accountUiTest.form=(...args)=>{
        const data=args.at(-1);let defaults={...data};
        const form=reactive({...data,errors:{},processing:false,clearErrors(){this.errors={};},get isDirty(){return Object.keys(defaults).some(key=>this[key]!==defaults[key]);},defaults(){defaults=Object.fromEntries(Object.keys(data).map(key=>[key,this[key]]));},reset(...keys){for(const key of keys.length?keys:Object.keys(defaults))this[key]=defaults[key];}});
        for(const method of ['post','patch','delete'])form[method]=(url,options={})=>{form.processing=true;calls.push({method,url,body:Object.fromEntries(Object.keys(data).map(key=>[key,form[key]])),options,form});};
        forms.push(form);return form;
    };
    globalThis.accountUiTest.get=(url,data,options)=>calls.push({method:'get',url,data,options});
    globalThis.accountUiTest.reload=(options)=>{calls.push({method:'reload',options});options.onStart?.();};
    globalThis.Document=class {};globalThis.ShadowRoot=class {};globalThis.document={activeElement:null};
    const root={children:[]};
    const renderer=createRenderer({
        createElement(type){return{type,tagName:type.toUpperCase(),props:{},children:[],style:{},listeners:{},scrollHeight:500,scrollTop:0,open:false,showModal(){this.open=true;},close(){this.open=false;},focus(){globalThis.document.activeElement=this;},querySelector(){return null;},getRootNode(){return globalThis.document;},value:'',addEventListener(name,fn){this.listeners[name]=fn;},removeEventListener(){},setAttribute(name,value){this.props[name]=value;},removeAttribute(name){delete this.props[name];},get options(){return this.children;}};},
        createText:text=>({text}),createComment:()=>({text:''}),
        insert(child,parent,anchor){if(child.parent)child.parent.children=child.parent.children.filter(node=>node!==child);child.parent=parent;const index=parent.children.indexOf(anchor);if(index<0)parent.children.push(child);else parent.children.splice(index,0,child);},remove(node){if(node.parent)node.parent.children=node.parent.children.filter(child=>child!==node);},setText:(node,text)=>node.text=text,setElementText:(node,text)=>{node.text=text;node.children=[];},parentNode:node=>node.parent,nextSibling:node=>node.parent?.children[node.parent.children.indexOf(node)+1],patchProp(node,key,before,after){node.props[key]=after;if(key==='value')node.value=after;},
    });
    const app=renderer.createApp({render:()=>h(Component,props)});app.config.globalProperties.$page=globalThis.accountUiTest.page;app.mount(root);
    const nodes=()=>{const result=[];function walk(node){result.push(node);node.children?.forEach(walk);}walk(root);return result;};
    const text=node=>[node.text||'',...(node.children||[]).map(text)].join(' ').replace(/\s+/g,' ').trim();
    const button=label=>nodes().find(node=>node.tagName==='BUTTON'&&text(node)===label);
    const click=async node=>{assert.ok(node,'Control exists');assert.ok(!node.props.disabled,'Control enabled');node.focus?.();for(const handler of [node.props.onClick].flat().filter(Boolean))await handler({preventDefault(){},stopPropagation(){}});await flush();};
    const input=async(node,value)=>{node.value=value;await(node.listeners.input||node.props.onInput)?.({target:node});await flush();};
    const submit=async(index=0)=>{const form=nodes().filter(node=>node.tagName==='FORM')[index];await form.props.onSubmit({preventDefault(){},stopPropagation(){}});await flush();};
    return{app,nodes,text:()=>text(root),button,click,input,submit,forms,calls};
}
const booking={id:'visit-one',salon:'Juniper Salon',reference:'VISIT-1',starts_at:'2026-11-10T15:00:00Z',time_zone:'America/Toronto',services:['Signature cut'],address:'123 Queen Street',status:'confirmed',salon_url:'/marketplace/salons/juniper',unread:2};
test('booking cards keep management, messages, history and favourites linked to the real visit',async()=>{
    const f=mount(Account,{bookings:{data:[booking],current_page:1},favourites:[{name:'Juniper Salon',url:booking.salon_url}],view:'upcoming'});
    try{assert.match(f.text(),/Your next visit/);assert.ok(f.nodes().some(node=>node.props?.href==='/marketplace/account?view=history'));assert.ok(f.nodes().some(node=>node.props?.href==='/marketplace/account/bookings/visit-one/messages'));await f.click(f.button('Manage booking'));assert.equal(f.calls[0].url,'/marketplace/account/bookings/visit-one/manage');assert.equal(f.button('Manage booking').props.disabled,true);}finally{f.app.unmount();}
});
test('profile form saves through the stable profile route and preserves work on validation failure',async()=>{
    const f=mount(Profile,{profile:{name:'Jordan Preview',email:'customer@example.test',phone:''}});
    try{assert.equal(f.button('Save changes').props.disabled,true);await f.input(f.nodes().find(node=>node.props?.autocomplete==='name'),'Jordan Updated');await f.submit();assert.equal(f.calls[0].url,'/marketplace/profile');assert.equal(f.calls[0].body.name,'Jordan Updated');f.calls[0].form.processing=false;f.forms[0].errors.name='Choose a name';await flush();assert.match(f.text(),/Choose a name/);assert.equal(f.forms[0].name,'Jordan Updated');await f.click(f.button('Discard'));assert.equal(f.forms[0].name,'Jordan Preview');}finally{f.app.unmount();}
});
test('profile avatar opens selection and saves the selected photo without submitting personal-detail drafts',async()=>{
    const originalCreate=URL.createObjectURL,originalRevoke=URL.revokeObjectURL;
    URL.createObjectURL=()=> 'blob:local-photo-preview';URL.revokeObjectURL=()=>{};
    const f=mount(Profile,{profile:{name:'Jordan Preview',email:'customer@example.test',phone:''}});
    try{
        const picker=f.nodes().find(node=>node.tagName==='BUTTON'&&node.props['aria-label']==='Add profile picture');
        const input=f.nodes().find(node=>node.props?.id==='customer-photo');let opened=0;input.click=()=>opened++;
        assert.notEqual(input.props.hidden,undefined);await f.click(picker);assert.equal(opened,1);
        await f.input(f.nodes().find(node=>node.props?.autocomplete==='name'),'Unsaved personal detail');
        const photo=new Blob(['synthetic-photo'],{type:'image/png'});
        await input.props.onChange({target:{files:[photo]}});await flush();
        assert.ok(f.nodes().some(node=>node.tagName==='IMG'&&node.props.src==='blob:local-photo-preview'));
        await f.submit(0);
        assert.equal(f.calls[0].url,'/marketplace/profile/photo');assert.equal(f.calls[0].body.photo,photo);
        assert.equal(f.calls[0].options.forceFormData,true);assert.equal(f.forms[0].name,'Unsaved personal detail');
        f.calls[0].form.processing=false;f.calls[0].options.onSuccess();await flush();
        assert.equal(f.forms[1].photo,null);assert.equal(f.forms[0].name,'Unsaved personal detail');
    }finally{f.app.unmount();URL.createObjectURL=originalCreate;URL.revokeObjectURL=originalRevoke;}
});
test('avatar removal requires its shared confirmation dialog and preserves personal-detail drafts',async()=>{
    const f=mount(Profile,{profile:{name:'Jordan Preview',email:'customer@example.test',phone:'',photo_url:'/marketplace/profile/photo?v=saved'}});
    try{
        const remove=f.nodes().find(node=>node.tagName==='BUTTON'&&node.props['aria-label']==='Remove profile photo');
        assert.equal(remove.props['aria-haspopup'],'dialog');assert.ok(f.nodes().some(node=>node.props?.role==='tooltip'));
        const dialog=f.nodes().find(node=>node.tagName==='DIALOG');assert.equal(dialog.open,false);
        await f.input(f.nodes().find(node=>node.props?.autocomplete==='name'),'Other unsaved edit');
        await f.click(remove);assert.equal(dialog.open,true);assert.equal(f.calls.length,0);
        assert.match(f.text(),/Remove your profile photo/);assert.equal(globalThis.document.activeElement.props.class,f.button('Keep photo').props.class);
        await f.click(f.button('Keep photo'));assert.equal(dialog.open,false);assert.equal(f.calls.length,0);
        assert.equal(globalThis.document.activeElement.props['aria-label'],'Remove profile photo');
        await f.click(remove);await f.click(f.button('Remove photo'));
        assert.equal(f.calls.length,1);assert.equal(f.calls[0].method,'delete');assert.equal(f.calls[0].url,'/marketplace/profile/photo');
        assert.equal(f.button('Remove photo').props.disabled,true);assert.equal(f.forms[0].name,'Other unsaved edit');
        f.calls[0].form.processing=false;f.calls[0].form.errors.photo='Your current photo is kept. Contact support.';await flush();
        assert.equal(dialog.open,true);assert.match(f.text(),/Your photo is kept/);assert.equal(f.forms[0].name,'Other unsaved edit');
        await f.click(f.button('Keep photo'));assert.equal(dialog.open,false);assert.equal(f.calls.length,1);
    }finally{f.app.unmount();}
});
test('message composer retains the replay key after failure and clears only after confirmed send',async()=>{
    const f=mount(Conversation,{booking,messages:[],paused:false});await flush();
    try{const composer=f.nodes().find(node=>node.props?.id==='message-body');await f.input(composer,'What should I bring?');const key=f.forms[0].idempotency_key;assert.ok(key);await f.submit();assert.equal(f.calls[0].url,'/marketplace/account/bookings/visit-one/messages');assert.equal(f.calls[0].body.idempotency_key,key);f.calls[0].form.processing=false;f.forms[0].errors.body='Please try later';await flush();assert.equal(f.forms[0].body,'What should I bring?');assert.equal(f.forms[0].idempotency_key,key);f.calls[0].options.onSuccess();await flush();assert.equal(f.forms[0].body,'');assert.notEqual(f.forms[0].idempotency_key,key);}finally{f.app.unmount();}
});
test('paused customer conversation removes the composer and keeps a working reopen action',async()=>{
    const f=mount(Conversation,{booking,messages:[],paused:true});await flush();
    try{assert.ok(!f.nodes().some(node=>node.props?.id==='message-body'));await f.click(f.button('Reopen messages'));assert.equal(f.calls[0].method,'patch');assert.equal(f.calls[0].body.paused,false);}finally{f.app.unmount();}
});
test('inbox refresh is explicit, disabled during loading and preserves conversation destinations',async()=>{
    const f=mount(Inbox,{threads:{data:[booking]}});
    try{assert.ok(f.nodes().some(node=>node.props?.href==='/marketplace/account/bookings/visit-one/messages'));await f.click(f.button('Refresh inbox'));assert.deepEqual(f.calls[0].options.only,['threads']);assert.equal(f.button('Refreshing…').props.disabled,true);f.calls[0].options.onFinish();await flush();assert.equal(f.button('Refresh inbox').props.disabled,false);}finally{f.app.unmount();}
});
test('shared customer navigation marks the current page and signs out only the customer',async()=>{
    const f=mount(Shell,{title:'Messages',active:'messages'});
    try{assert.ok(f.nodes().some(node=>node.props?.href==='/marketplace/messages'&&node.props['aria-current']==='page'));await f.click(f.button('Log out'));assert.equal(f.calls[0].url,'/marketplace/sign-out');}finally{f.app.unmount();}
});

const businessInbox={url:'/businesses/sample/app/marketplace',canContact:true,q:'',view:'all',threads:{data:[{id:booking.id,name:'Jordan',url:'/businesses/sample/app/marketplace/messages/visit-one',reference:'VISIT-1',starts_at:'10 Nov 2026',preview:'Can I arrive early?',unread:1}],current_page:1}};
test('Business Messages renders a focused inbox with functional search, unread filtering and a guarded refresh',async()=>{
    const f=mount(BusinessInbox,{businessLabel:'Sample Studio',inbox:businessInbox});
    try{
        assert.match(f.text(),/Select a conversation/);assert.match(f.text(),/Can I arrive early/);
        assert.doesNotMatch(f.text(),/Meet your next regular|listing|Booking entry activity|salon inbox/);
        await f.input(f.nodes().find(node=>node.props?.id==='conversation-search'),'Jordan');await f.submit();
        assert.equal(f.calls[0].url,businessInbox.url);assert.deepEqual(f.calls[0].data,{q:'Jordan',view:'all'});
        await f.click(f.button('Unread'));assert.equal(f.calls[1].data.view,'unread');
        await f.click(f.button('Refresh'));assert.deepEqual(f.calls[2].options.only,['inbox','messages','paused']);assert.equal(f.button('Refreshing…').props.disabled,true);
        f.calls[2].options.onFinish();await flush();assert.equal(f.button('Refresh').props.disabled,false);
    }finally{f.app.unmount();}
});
test('Business conversation shares the inbox and preserves a reply through refresh and send failure',async()=>{
    const f=mount(Conversation,{businessView:true,businessLabel:'Sample Studio',customerName:'Jordan',inbox:businessInbox,booking,messages:[],postUrl:'/businesses/sample/app/marketplace/messages/visit-one',canReply:true,paused:false});await flush();
    try{
        assert.match(f.text(),/Booking details/);assert.doesNotMatch(f.text(),/Conversation options|Meet your next regular/);
        assert.ok(f.nodes().some(node=>node.props?.['aria-current']==='page'));
        await f.input(f.nodes().find(node=>node.props?.id==='message-body'),'Please arrive ten minutes early.');
        const key=f.forms[0].idempotency_key;await f.click(f.button('Refresh'));assert.equal(f.forms[0].body,'Please arrive ten minutes early.');
        await f.submit(1);assert.equal(f.calls[1].url,'/businesses/sample/app/marketplace/messages/visit-one');
        f.forms[0].processing=false;f.forms[0].errors.body='Your message was not sent. Try later.';await flush();
        assert.equal(f.forms[0].idempotency_key,key);assert.equal(f.forms[0].body,'Please arrive ten minutes early.');assert.match(f.text(),/Your message was not sent/);
    }finally{f.app.unmount();}
});

test('Business message recovery keeps a native support action available while a failed mutation is blocked',async()=>{
    const f=mount(Recovery,{business:true},{errors:{marketplace:'Your message remains on this page. Contact support.'},marketplaceBlocked:true,marketplaceRetryAt:Date.now()+3000});
    try{
        assert.match(f.text(),/Your message remains on this page/);assert.match(f.text(),/Try again in/);
        const support=f.nodes().find(node=>node.props?.href==='mailto:support@example.test');
        assert.ok(support);assert.match(support.props.class,/cd-app-button/);
        assert.equal(f.nodes().find(node=>node.tagName==='FIELDSET').props.disabled,true);
    }finally{f.app.unmount();}
});

test('Business listing follows published booking setup with one optional visibility form and no profile approval step',async()=>{
    const listing={listed:true,version:3,ready:true,preview_url:'/preview',url:'/marketplace/salons/sample',save_url:'/businesses/sample/app/marketplace/listing',setup_url:'/businesses/sample/setup'};
    const f=mount(BusinessListing,{businessLabel:'Sample Studio',canManageListing:true,listing,publication:{approved:true,status:'approved',ready:true,report_url:'/outcomes'},stats:{bookings:0,started:0}});
    try{
        assert.match(f.text(),/Listed on Marketplace/);assert.match(f.text(),/Publishing your booking page makes your business searchable/);
        assert.doesNotMatch(f.text(),/Request publication review|awaiting review|I agree|pilot|SEO score/);
        assert.equal(f.nodes().filter(node=>node.tagName==='FORM').length,1);
        await f.submit();assert.equal(f.calls[0].url,listing.save_url);assert.equal(f.calls[0].body.version,3);assert.equal(f.calls[0].body.consent,true);
    }finally{f.app.unmount();}
});

test('footer cookie action reopens saved choices and rejection remains functional without an extra settings strip',async()=>{
    const priorWindow=globalThis.window,priorStorage=globalThis.localStorage;
    const values=new Map([[ACQUISITION_CONSENT_KEY,JSON.stringify({version:'mpg-consent-v1',choice:'rejected',expires_at:Date.now()+86400000})]]);
    const browser=new EventTarget();browser.navigator={};browser.location={href:'https://example.test/marketplace'};
    browser.localStorage={getItem:key=>values.get(key)||null,setItem:(key,value)=>values.set(key,value)};
    globalThis.window=browser;globalThis.localStorage=browser.localStorage;
    const calls=[];globalThis.accountUiTest.axios={post:async()=>calls.push('post'),delete:async()=>calls.push('delete')};
    const f=mount(AcquisitionConsent,{}, {acquisitionPublic:{page:'/marketplace',kind:'entry'}});await flush();
    try{
        assert.equal(f.nodes().filter(node=>node.tagName==='BUTTON').length,0);
        openAcquisitionPreferences();await flush();assert.match(f.text(),/Your cookie choices/);
        await f.click(f.button('Reject optional cookies'));assert.equal(JSON.parse(values.get(ACQUISITION_CONSENT_KEY)).choice,'rejected');assert.deepEqual(calls,['delete']);
        assert.ok(!f.nodes().some(node=>node.tagName==='ASIDE'));
        openAcquisitionPreferences();await flush();await f.click(f.button('Accept optional cookies'));
        assert.equal(JSON.parse(values.get(ACQUISITION_CONSENT_KEY)).choice,'accepted');assert.deepEqual(calls,['delete','post']);
    }finally{f.app.unmount();globalThis.window=priorWindow;globalThis.localStorage=priorStorage;}
});
