import puppeteer from 'puppeteer';
import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
const base = 'http://127.0.0.1:8139';
const output = 'docs/audits/2026-10-24-help-center/browser';
await mkdir(output, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: process.env.HELP_BROWSER || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--no-sandbox'] });
const page = await browser.newPage(), records = [], errors = [];
page.on('pageerror', error => errors.push(error.message));
const go = async path => { const response = await page.goto(base+path, { waitUntil: 'networkidle2' }); assert.equal(response.status(),200,path); await page.evaluate(async () => { await document.fonts.ready; for (const image of document.images) image.loading = 'eager'; await Promise.all([...document.images].map(image => image.complete ? null : new Promise(resolve => { image.onload = resolve; image.onerror = resolve; }))); }); };
const geometry = () => page.evaluate(() => ({ h1:document.querySelectorAll('h1').length,main:document.querySelectorAll('main').length,overflow:document.documentElement.scrollWidth>innerWidth+1,broken:[...document.images].filter(img=>!img.naturalWidth).map(img=>img.src),canonical:[...document.querySelectorAll('link[rel=canonical]')].map(el=>el.href),unlabelledButtons:[...document.querySelectorAll('button')].filter(el=>!el.innerText.trim()&&!el.getAttribute('aria-label')).length }));
const save = name => page.screenshot({ path:`${output}/${name}.jpg`,quality:83,fullPage:true });
try {
    for (const width of [320,390,768,1024,1440]) {
        await page.setViewport({width,height:900});
        for (const path of ['/help','/help/category/team','/help/articles/team-add-member','/help/articles/checkout-record-payments','/help/articles/billing-recovery','/help?q=add+worker','/help?q=zzzzzxxyy']) {
            await go(path);
            const checks=await geometry(); records.push({path,width,...checks});
            assert.equal(checks.h1,1,`${width} ${path} h1`);assert.equal(checks.main,1);assert.equal(checks.overflow,false,`${width} ${path} overflow`);assert.deepEqual(checks.broken,[]);assert.equal(checks.unlabelledButtons,0);assert.equal(checks.canonical.length,1);
            if ((width===390||width===1440)&&['/help','/help/articles/team-add-member','/help?q=zzzzzxxyy'].includes(path)) await save(`${path==='/help'?'index':path.includes('?')?'empty':'article'}-${width}`);
        }
    }
    await page.setViewport({width:1440,height:900});await go('/help');
    await page.type('#help-search-main','add worker');await page.waitForSelector('.help-suggestions a');await page.keyboard.press('ArrowDown');
    assert.ok(await page.$eval('#help-search-main',el=>el.getAttribute('aria-activedescendant')));
    await Promise.all([page.waitForNavigation({waitUntil:'networkidle2'}),page.keyboard.press('Enter')]);
    assert.ok(page.url().includes('/help/articles/')); records.push({check:'keyboard suggestions',passed:true});
    await go('/help/articles/team-add-member');await page.click('.help-screenshot button');assert.ok(await page.$('dialog[open]'));await page.keyboard.press('Escape');assert.equal(await page.$('dialog[open]'),null);assert.ok(await page.evaluate(()=>document.activeElement.closest('.help-screenshot')));records.push({check:'image Escape and focus return',passed:true});
    await page.setRequestInterception(true);let searchRequests=0,failFeedback=false;
    const intercept=request=>{
        if (request.url().includes('/help/search?')) { searchRequests++;request.respond({status:429,headers:{'content-type':'application/json','Retry-After':'2'},body:'{}'}); }
        else if (request.url().endsWith('/feedback')&&failFeedback) request.respond({status:429,headers:{'content-type':'application/json','Retry-After':'2'},body:'{}'});
        else request.continue();
    };page.on('request',intercept);
    await go('/help');await page.type('#help-search-main','team');await page.waitForFunction(()=>document.body.innerText.includes('Search suggestions paused'));await page.type('#help-search-main',' member');assert.equal(searchRequests,1);await save('search-cooldown-desktop');records.push({check:'Retry-After blocks requests while typing',requests:searchRequests});
    await page.setRequestInterception(false);page.off('request',intercept);
    await go('/help/articles/team-add-member');await page.click('.help-feedback button');await page.waitForFunction(()=>document.body.innerText.includes('Thank you for your feedback'));records.push({check:'real local feedback saved',passed:true});await save('feedback-success-desktop');
    await page.setRequestInterception(true);failFeedback=true;page.on('request',intercept);
    for(const width of [320,390,1440]) {await page.setViewport({width,height:900});await go('/help/articles/team-add-member');await page.click('.help-feedback button');await page.waitForFunction(()=>document.body.innerText.includes('Your feedback could not be saved'));assert.equal(await page.$eval('.help-feedback button',el=>el.disabled),true);await save(`feedback-cooldown-${width}`);await page.waitForFunction(()=>!document.querySelector('.help-feedback button').disabled,{timeout:7000});assert.equal((await geometry()).overflow,false);}
    page.off('request',intercept);await page.setRequestInterception(false);records.push({check:'feedback cooldown desktop and phones',passed:true});
    const fixture=JSON.parse(await readFile('tmp/help-center/private-capture.json','utf8'));
    await page.setViewport({width:1440,height:900});await go('/login');await page.type('#email',fixture.email);await page.type('#password',fixture.password);await Promise.all([page.waitForNavigation({waitUntil:'networkidle2'}),page.click('button[type=submit]')]);
    await go(`/businesses/${fixture.workspaces['team-workspace-review']}/app/team`);await page.click('.cd-help-menu summary');assert.ok(await page.$eval('.cd-help-popover',el=>el.innerText.includes('Add a team member')));await save('contextual-team-desktop');
    await page.setViewport({width:390,height:844});await page.click('.cd-help-menu summary');await page.click('.cd-help-menu summary');assert.equal((await geometry()).overflow,false);await save('contextual-team-mobile');
    await go('/help/open/services');assert.ok(await page.$('.help-workspace-list'));assert.equal((await geometry()).overflow,false);records.push({check:'authenticated workspace chooser',passed:true});
    const nojs=await browser.newPage();await nojs.setJavaScriptEnabled(false);await nojs.goto(base+'/help/articles/team-add-member');const text=await nojs.$eval('body',el=>el.innerText);assert.ok(text.includes('Create the working profile')&&text.includes('Save & invite'));records.push({check:'article readable without JavaScript and SSR',passed:true});await nojs.close();
    assert.deepEqual(errors,[]);
    await writeFile(`${output}/results.json`,JSON.stringify({records,errors},null,2)+'\n');console.log(`${records.length} responsive and journey checks passed.`);
} finally { await writeFile(`${output}/last-run.json`,JSON.stringify({records,errors},null,2)+'\n');await browser.close(); }
