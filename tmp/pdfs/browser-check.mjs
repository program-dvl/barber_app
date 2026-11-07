import puppeteer from 'puppeteer';
import {readFile,writeFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const root=process.cwd();
const browser=await puppeteer.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
const page=await browser.newPage();
await page.setRequestInterception(true);
page.on('request',async request=>{
 const url=new URL(request.url());
 if(request.isNavigationRequest())return request.respond({status:200,contentType:'text/html',body:await readFile(resolve(root,'tmp/pdfs/settings.html'))});
 const file=resolve(root,'public','.'+url.pathname);
 try{let body=await readFile(file);const contentType={'.js':'application/javascript','.css':'text/css','.svg':'image/svg+xml','.woff2':'font/woff2','.ttf':'font/ttf'}[extname(file)]||'application/octet-stream';return request.respond({status:200,headers:{'Access-Control-Allow-Origin':'*'},contentType,body});}catch{return request.abort();}
});
const errors=[];page.on('pageerror',err=>{errors.push(err.message);process.stderr.write(err.message+'\n');});
const evidence=[];
for(const width of [1440,390]){
 await page.setViewport({width,height:1000});await page.goto('http://example.test/settings.html',{waitUntil:'networkidle0'});
 await page.waitForSelector('input[role="switch"]');
 const texts=await page.locator('body').map(el=>el.innerText).wait();
 if(!texts.includes('Automatically email review requests'))throw Error('Missing review preference');
 evidence.push({width,overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),toggles:await page.evaluate(()=>Array.from(document.querySelectorAll('input[type=checkbox]')).map(el=>({checked:el.checked,label:el.closest('label')?.innerText}))) });
 await page.screenshot({path:`docs/audits/2026-11-05-visit-emails/settings-${width}.png`,fullPage:true});
}
await writeFile('docs/audits/2026-11-05-visit-emails/browser.json',JSON.stringify({evidence,errors},null,2));await browser.close();
