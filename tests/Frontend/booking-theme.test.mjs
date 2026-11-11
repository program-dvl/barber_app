import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { bookingTheme } from '../../resources/js/Support/bookingTheme.js';

function luminance(hex) {
    const values = hex.slice(1).match(/../g).map(value => parseInt(value, 16) / 255)
        .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    return values[0] * 0.2126 + values[1] * 0.7152 + values[2] * 0.0722;
}
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + 0.05) / (Math.min(luminance(a), luminance(b)) + 0.05);

test('saved accents retain their exact decorative colour with readable actions across light booking surfaces', () => {
    for (const color of ['#F06445', '#172554', '#ffffff', '#000000', '#ffff00', '#00ffff', '#00ff00', '#ff00ff', '#fefefe', '#6D4AFF']) {
        const theme = bookingTheme(color);
        assert.equal(theme['--booking-accent'], color.toLowerCase());
        for (const surface of ['#ffffff', '#eef2f7', theme['--status-info-soft']]) {
            assert.ok(contrast(theme['--action-primary'], surface) >= 4.5, `${color} is readable on ${surface}`);
        }
        assert.ok(contrast(theme['--action-primary-hover'], '#ffffff') >= 4.5);
        assert.ok(contrast(theme['--action-primary'], '#ffffff') >= 4.5);
        assert.deepEqual(Object.keys(theme).filter(key => /danger|warning|success|brand-primary/.test(key)), []);
    }
});

test('absent and malformed saved values inherit the platform theme without accepting CSS injection', () => {
    for (const value of [null, undefined, '', '#abc', 'orange', '#123456;', '#123456; background:url(https://example.test)', {}, 123]) {
        assert.deepEqual(bookingTheme(value), {});
    }
});

const pageState = {props:{errors:{},brand:{support_email:'support@example.test'}}};
const Head={render(){return null}};
const Link={props:['href'],setup(props,{attrs,slots}){return()=>h('a',{...attrs,href:props.href},slots.default?.());}};
const AppNotice={props:['title','description'],setup(props,{slots}){return()=>h('div',{'data-shared-notice':''},[props.title,props.description,slots.actions?.()]);}};
globalThis.bookingShellTest={inertia:{Head,Link,usePage:()=>pageState,router:{on:()=>()=>{}}},AppNotice};
async function compileActual(path) {
    const {descriptor}=parse(await readFile(resolve(path),'utf8'));
    let code=compileScript(descriptor,{id:path,inlineTemplate:true}).content
        .replace(/from (['"])vue\1/g,`from '${pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href}'`)
        .replace(/import \{([^}]+)\} from '@inertiajs\/vue3';/g,(_,names)=>`const {${names}}=globalThis.bookingShellTest.inertia;`)
        .replace(/import \{([^}]+)\} from '@heroicons\/vue\/24\/outline';/g,(_,names)=>names.split(',').map(name=>`const ${name.trim()}={render(){return null}};`).join('\n'))
        .replace(/import ProductMark from '[^']+';/,`const ProductMark={render(){return globalThis.bookingShellTest.h('span',{'data-product-mark':''},'Business mark')}};`)
        .replace(/import BookingSkeleton from '[^']+';/,`const BookingSkeleton={render(){return null}};`)
        .replace(/import AppNotice from '[^']+';/,`const AppNotice=globalThis.bookingShellTest.AppNotice;`)
        .replace(/import MarketplaceLayout from '[^']+';/,`const MarketplaceLayout=globalThis.bookingShellTest.MarketplaceLayout;`)
        .replace(/import marketplaceMark from '[^']+';/,`const marketplaceMark='/assets/clipperdesk-marketplace-mark.svg';`)
        .replace(/import '[^']+\.css';/g,'')
        .replace("from '@/Support/marketplaceAttribution';",`from '${pathToFileURL(resolve('resources/js/Support/marketplaceAttribution.js')).href}';`)
        .replace("from '@/Support/bookingTheme';",`from '${pathToFileURL(resolve('resources/js/Support/bookingTheme.js')).href}';`)
        .replace("from '@/Support/useActionMenus';",`from '${pathToFileURL(resolve('resources/js/Support/useActionMenus.js')).href}';`);
    return(await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`)).default;
}
globalThis.bookingShellTest.h=h;
globalThis.bookingShellTest.MarketplaceLayout=await compileActual('resources/js/Layouts/MarketplaceLayout.vue');
const actualLayout=()=>compileActual('resources/js/Layouts/PublicBookingLayout.vue');

test('the actual public layout scopes business branding to its root for both public and private preview pages', async () => {
    const Layout = await actualLayout();
    for (const preview of [false, true]) {
        const app = createSSRApp({ render: () => h('div', [
            h(Layout, { title: 'Orange Studio', currentStep: 1, preview, brandColor: '#F06445' }),
            h(Layout, { title: 'Default Studio', currentStep: 1, preview }),
        ]) });
        app.config.globalProperties.route = () => '/';
        const html = await renderToString(app);
        assert.equal((html.match(/--booking-accent:#f06445/g) || []).length, 1);
        assert.ok(html.includes(`--action-primary:${bookingTheme('#F06445')['--action-primary']}`));
        assert.ok(html.includes('var(--booking-accent,var(--action-primary))'));
        assert.equal((html.match(/class="cd-product-flow/g) || []).length, 2);
    }
});


test('Marketplace booking reuses its actual header, logo, canvas wrapper and footer at every step',async()=>{
    const Layout=await actualLayout();
    for(const currentStep of [1,2,3,4]){
        pageState.props={errors:{},brand:{support_email:'support@example.test'}};
        const app=createSSRApp({render:()=>h(Layout,{marketplace:true,title:'Sample Studio',currentStep,steps:['Services','Staff & time','Confirm','Booked']},()=>h('section',{'data-business-content':''},'Sample Studio · CAD 35'))});
        app.config.globalProperties.route=()=>'/';
        const html=await renderToString(app);
        assert.match(html,/class="market"/);assert.match(html,/class="mp-booking-flow"/);
        assert.match(html,/clipperdesk-marketplace-mark\.svg/);assert.match(html,/Clipperdesk<small>marketplace/);
        assert.match(html,/href="\/marketplace\/sign-in"/);assert.match(html,/Sample Studio · CAD 35/);
        assert.match(html,new RegExp(`Step ${currentStep} of 4`));
        assert.doesNotMatch(html,/data-product-mark|cd-product-flow|Manage appointment/);
        assert.equal((html.match(/<footer/g)||[]).length,1);
        assert.equal((html.match(/id="marketplace-main"/g)||[]).length,1);
    }
});

test('business accents stay inside Marketplace booking while account navigation and feedback remain shared',async()=>{
    const Layout=await actualLayout();
    pageState.props={errors:{},brand:{support_email:'support@example.test'},marketplaceCustomer:{name:'Customer Sample',email:'customer@example.test',photo_url:'/marketplace/profile/photo?v=own'},marketplaceStatus:'Your favourite is saved.'};
    const app=createSSRApp({render:()=>h(Layout,{marketplace:true,currentStep:1,brandColor:'#F06445'},()=>h('div','Business catalogue'))});
    app.config.globalProperties.route=()=>'/';
    const html=await renderToString(app);
    const header=html.match(/<header[\s\S]*?<\/header>/)[0];
    assert.match(header,/href="\/marketplace\/account"/);assert.match(header,/href="\/marketplace\/messages"/);
    assert.match(header,/href="\/marketplace\/profile"/);assert.match(header,/photo\?v=own/);
    assert.doesNotMatch(header,/f06445|--booking-accent/);
    assert.match(html,/class="mp-booking-flow" style="--booking-accent:#f06445/);
    assert.equal((html.match(/Your favourite is saved\./g)||[]).length,1);
});


test('private customer management, review, waitlist and forms share Marketplace without a login or booking progress step',async()=>{
    const Layout=await actualLayout();pageState.props={errors:{},brand:{support_email:'support@example.test'}};
    for(const title of ['Manage an appointment','Share your experience','Waitlist opening','Your form','Form received']){
        const app=createSSRApp({render:()=>h(Layout,{title,mode:'self-service'},()=>h('section',{'data-private-content':''},title))});
        app.config.globalProperties.route=()=>'/';
        const html=await renderToString(app);
        assert.match(html,/Clipperdesk Marketplace home/);assert.match(html,/mp-self-service-main/);
        assert.match(html,/data-private-content/);assert.match(html,new RegExp(title));
        assert.doesNotMatch(html,/data-product-mark|cd-product-flow|Step 0 of|Ready to start/);
    }
});

test('cookie settings stays inside the actual Marketplace or direct-booking footer',async()=>{
    const Layout=await actualLayout();
    pageState.props={errors:{},brand:{support_email:'support@example.test'},acquisitionPublic:{page:'/marketplace',kind:'entry'}};
    try{
        for(const marketplace of [true,false]){
            const app=createSSRApp({render:()=>h(Layout,{marketplace,title:'Sample Studio'})});
            app.config.globalProperties.route=()=>'/';
            const html=await renderToString(app);
            const footer=html.match(/<footer[\s\S]*?<\/footer>/)?.[0];
            assert.ok(footer);assert.match(footer,/Cookie settings/);assert.equal((html.match(/Cookie settings/g)||[]).length,1);
            assert.doesNotMatch(html,/acquisition-choice/);
        }
    }finally{pageState.props={errors:{},brand:{support_email:'support@example.test'}};}
});
