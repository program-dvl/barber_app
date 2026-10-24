import puppeteer from 'puppeteer';
import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';
const base='http://127.0.0.1:8139';
const browser=await puppeteer.launch({headless:true,executablePath:process.env.HELP_BROWSER||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',args:['--no-sandbox']});
const page=await browser.newPage();const records=[];let calls=0;
try {
    await page.setViewport({width:390,height:844});await page.setRequestInterception(true);
    page.on('request',request=>request.url().includes('/help/search?')?(calls++,request.respond({status:503,headers:{'content-type':'application/json'},body:'{}'})):request.continue());
    await page.goto(base+'/help',{waitUntil:'networkidle2'});await page.type('#help-search-main','service');await page.waitForFunction(()=>document.body.innerText.includes('Search suggestions paused'));await page.type('#help-search-main',' price');assert.equal(calls,1);assert.equal(await page.$eval('#help-search-main',el=>el.value),'service price');
    await Promise.all([page.waitForNavigation({waitUntil:'networkidle2'}),page.click('.help-search-form button')]);assert.ok(await page.$('.help-result-list a'));records.push({check:'persistent suggestion failure stops requests; input retained and full Search progresses',passed:true});
    await page.goto(base+'/help/articles/services-create',{waitUntil:'networkidle2'});await page.evaluate(()=>[...document.querySelectorAll('.help-feedback button')].find(el=>el.innerText==='I still need help').click());await page.waitForFunction(()=>document.body.innerText.includes('Let’s find your next step'));assert.ok(await page.$('.help-feedback a[href^="mailto:"]'));assert.ok(await page.$('.help-feedback a[href="/help"]'));records.push({check:'unhelpful feedback offers support and another search',passed:true});
    const nojs=await browser.newPage();await nojs.setJavaScriptEnabled(false);await nojs.goto(base+'/help/articles/team-add-member',{waitUntil:'networkidle2'});assert.equal(await nojs.$eval('.help-prose ol',el=>getComputedStyle(el).listStyleType),'decimal');assert.ok(await nojs.$('footer a[href$="/help"]'));records.push({check:'no-JavaScript steps remain numbered with Help navigation',passed:true});await nojs.close();
    await writeFile('docs/audits/2026-10-24-help-center/browser/recovery-results.json',JSON.stringify(records,null,2)+'\n');console.log(`${records.length} additional recovery checks passed.`);
}finally{await browser.close();}
