import { readFile, writeFile } from 'node:fs/promises';
const [originArg,manifestFile,outputFile] = process.argv.slice(2);
const origin=new URL(originArg);
if(!['http:','https:'].includes(origin.protocol) || origin.pathname!=='/' || origin.search || origin.hash || origin.username || origin.password) throw Error('Use an exact origin.');
const manifest=JSON.parse(await readFile(manifestFile,'utf8'));
const paths=['/marketplace',manifest.local,manifest.profile,'/marketplace?q=Dog%20grooming',manifest.local+'?page=99','/marketplace/profile','/marketplace/profile/photo','/marketplace/sign-in','/marketplace/account','/appointments/secure/not-a-real-token','/marketplace/zz/unknown/unknown','/marketplace/sitemap.xml'];
const results=[];
for(const path of paths){
    const url=new URL(path,origin);
    if(url.origin!==origin.origin || !url.pathname.startsWith('/')) throw Error('Off-origin path refused.');
    const start=performance.now();
    const response=await fetch(url,{redirect:'manual',headers:{'User-Agent':'ClipperdeskLocalCrawl/1.0'},signal:AbortSignal.timeout(20000)});
    const body=await response.text();
    if(body.length>2*1024*1024) throw Error('Response exceeds crawl budget.');
    const visible=body.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi,'');
    const canonical=body.match(/<link\b[^>]*rel="canonical"[^>]*href="([^"]+)"/i)?.[1] ?? null;
    const robots=response.headers.get('x-robots-tag');
    const result={path,status:response.status,robots,canonical,cache:response.headers.get('cache-control'),h1:(visible.match(/<h1\b/gi)||[]).length,links:(visible.match(/<a\s/gi)||[]).length,publicText:visible.includes(path===manifest.profile ? (manifest.profile_service || 'Skin fade') : 'Dog grooming'),ssrFallback:visible.includes('data-marketplace-fallback'),milliseconds:Math.round(performance.now()-start),bytes:Buffer.byteLength(body)};
    if(!robots?.includes('noindex')) throw Error('Expected default-off local crawl: '+path);
    if([manifest.local,manifest.profile].includes(path) && (response.status!==200 || !result.publicText || result.h1!==1 || result.links<3)) throw Error('Missing initial public HTML: '+path);
    if(/(?:account|profile\/photo|sign-in|secure)/.test(path) && !result.cache?.includes('no-store')) throw Error('Private cache policy missing: '+path);
    results.push(result);
}
const report={origin:origin.origin,observed_at:new Date().toISOString(),mode:'Local synthetic inventory; global indexation disabled',results};
if(outputFile)await writeFile(outputFile,JSON.stringify(report,null,2));
console.log(JSON.stringify(report,null,2));
