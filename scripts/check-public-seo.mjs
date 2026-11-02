#!/usr/bin/env node
// Read-only original-HTML check. Passing this does not establish Google indexing or ranking.
import { writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const publicPath = /^(?:\/$|\/(?:blog|company|features|help|pricing|privacy-policy|resources|security|solutions|use-cases)$|\/(?:blog|features|guides|solutions|use-cases)\/[a-z0-9]+(?:-[a-z0-9]+)*$|\/help\/(?:articles|category)\/[a-z0-9]+(?:-[a-z0-9]+)*$)/;
const decode = value => value.replace(/&(?:amp|quot|apos|lt|gt|#39|#(\d+)|#x([0-9a-f]+));/gi, (entity, decimal, hex) => {
    if (decimal || hex) {
        const point = Number.parseInt(decimal || hex, hex ? 16 : 10);
        return point <= 0x10ffff ? String.fromCodePoint(point) : entity;
    }
    return ({ '&amp;': '&', '&quot;': '"', '&apos;': "'", '&#39;': "'", '&lt;': '<', '&gt;': '>' })[entity.toLowerCase()] || entity;
});
const cleanText = html => decode(html.replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim();
const attrs = tag => Object.fromEntries([...tag.matchAll(/([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/g)].map(m => [m[1].toLowerCase(), decode(m[2] ?? m[3] ?? m[4])]));

export function parseSitemap(xml, origin) {
    const root = xml.match(/^\s*(?:<\?xml[^>]*>\s*)?<urlset\b[^>]*xmlns=["']http:\/\/www\.sitemaps\.org\/schemas\/sitemap\/0\.9["'][^>]*>([\s\S]*)<\/urlset>\s*$/);
    if (!root || /<!DOCTYPE|<!ENTITY/i.test(xml)) {
        throw new Error('Expected a standard XML urlset sitemap, without external entities.');
    }
    const urls = [];
    const remainder = root[1].replace(/<url\s*>\s*<loc\s*>([^<]+)<\/loc>\s*(?:<(lastmod|changefreq|priority)>[^<]*<\/\2>\s*)*<\/url>/g, (_entry, loc) => { urls.push(decode(loc.trim())); return ''; });
    if (remainder.trim() || !urls.length || urls.length > 1000) throw new Error('Expected 1–1000 complete sitemap URL entries.');
    if (new Set(urls).size !== urls.length) throw new Error('Duplicate sitemap URLs.');
    for (const value of urls) {
        const url = new URL(value);
        if (url.origin !== origin || url.username || url.password || url.search || url.hash || !publicPath.test(url.pathname)) {
            throw new Error('Sitemap contains a URL outside the permitted public routes on this origin.');
        }
    }
    return urls;
}

export function inspectPage(record) {
    const html = record.body || '';
    const markup = html.replace(/<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, '').replace(/<style\b[^>]*>[\s\S]*?<\/style\s*>/gi, '');
    const meta = [...markup.matchAll(/<meta\b[^>]*>/gi)].map(m => attrs(m[0]));
    const links = [...markup.matchAll(/<link\b[^>]*>/gi)].map(m => attrs(m[0]));
    const titles = [...markup.matchAll(/<title\b[^>]*>([\s\S]*?)<\/title\s*>/gi)].map(m => cleanText(m[1]));
    const descriptions = meta.filter(m => m.name?.toLowerCase() === 'description').map(m => m.content || '');
    const canonicals = links.filter(l => l.rel?.toLowerCase().split(/\s+/).includes('canonical')).map(l => l.href);
    const robots = meta.filter(m => ['robots', 'googlebot'].includes(m.name?.toLowerCase())).map(m => m.content || '');
    const h1 = [...markup.matchAll(/<h1\b[^>]*>([\s\S]*?)<\/h1\s*>/gi)].map(m => cleanText(m[1]));
    const main = [...markup.matchAll(/<main\b[^>]*>([\s\S]*?)<\/main\s*>/gi)].map(m => cleanText(m[1]));
    const anchorCount = [...markup.matchAll(/<a\b[^>]*\bhref\s*=/gi)].length;
    const schemas = [...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi)].filter(m => attrs(m[1]).type === 'application/ld+json');
    const problems = [];
    if (record.error) problems.push(record.error);
    if (record.status !== 200) problems.push(`HTTP ${record.status ?? 'request failed'}`);
    if (!record.contentType?.includes('text/html')) problems.push('Expected HTML');
    if (titles.length !== 1 || !titles[0]) problems.push('Expected one nonblank title');
    if (descriptions.length !== 1 || !descriptions[0]) problems.push('Expected one nonblank description');
    if (canonicals.length !== 1 || canonicals[0] !== record.url) problems.push('Expected one self-referencing canonical');
    if (robots.length !== 1 || !/\bindex\b/i.test(robots[0]) || /\b(?:noindex|none|nofollow)\b/i.test([...robots, record.robotsHeader || ''].join(','))) problems.push('Indexing/follow directive missing or restrictive');
    if (h1.length !== 1 || !h1[0]) problems.push('Expected one server-rendered H1');
    if (main.length !== 1 || main[0].split(/\s+/).length < 20) problems.push('Expected readable server-rendered main content');
    if (!anchorCount) problems.push('No server-rendered links');
    if (!schemas.length) problems.push('Missing structured data');
    for (const schema of schemas) {
        try { JSON.parse(schema[2]); } catch { problems.push('Invalid JSON-LD'); }
    }
    return { url: record.url, status: record.status, seconds: record.seconds, titles, descriptions, canonicals, robots, robotsHeader: record.robotsHeader, h1, mainWords: main[0]?.split(/\s+/).length || 0, anchorCount, schemaCount: schemas.length, problems };
}

export function inspectRobots(record, origin) {
    const problems = [];
    if (record.error) problems.push(record.error);
    if (record.status !== 200) problems.push(`Robots must return HTTP 200; received ${record.status ?? 'request failure'}`);
    if (!record.contentType?.includes('text/plain')) problems.push('Expected plain-text robots');
    if (!record.body?.split(/\r?\n/).some(line => line.trim() === `Sitemap: ${origin}/sitemap.xml`)) problems.push('Missing canonical sitemap declaration');
    if (/^\s*Disallow:\s*\/\s*(?:#.*)?$/mi.test(record.body || '')) problems.push('Robots contains a site-wide crawl block; review its user-agent group');
    return { url: record.url, status: record.status, seconds: record.seconds, problems };
}

async function get(url) {
    const start = performance.now();
    try {
        const response = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(20000), headers: { 'User-Agent': 'ClipperDesk-public-SEO-check/1.0', Accept: 'text/html,application/xml,text/plain' } });
        const chunks = []; let size = 0;
        for await (const chunk of response.body) {
            size += chunk.length;
            if (size > 2 * 1024 * 1024) throw new Error('Response exceeds the 2 MiB check limit');
            chunks.push(chunk);
        }
        return { url, status: response.status, seconds: Number(((performance.now() - start) / 1000).toFixed(3)), contentType: response.headers.get('content-type') || '', robotsHeader: response.headers.get('x-robots-tag') || '', body: Buffer.concat(chunks).toString('utf8') };
    } catch (error) { return { url, error: error.name === 'TimeoutError' ? 'Request timed out after 20 seconds' : error.message }; }
}

export async function checkPublicSeo(origin) {
    const sitemap = await get(`${origin}/sitemap.xml`);
    const robots = inspectRobots(await get(`${origin}/robots.txt`), origin);
    const report = { checkedAt: new Date().toISOString(), origin, scope: 'Original HTTP HTML only. Does not establish Google crawling, indexing or rankings.', sitemap: { url: sitemap.url, status: sitemap.status, seconds: sitemap.seconds, problems: [] }, robots, pages: [] };
    if (sitemap.error) report.sitemap.problems.push(sitemap.error);
    if (sitemap.status !== 200) report.sitemap.problems.push(`Expected HTTP 200; received ${sitemap.status ?? 'request failure'}`);
    if (!sitemap.contentType?.includes('xml')) report.sitemap.problems.push('Expected an XML content type');
    let urls = [];
    try { urls = parseSitemap(sitemap.body || '', origin); } catch (error) { report.sitemap.problems.push(error.message); }
    // No crawling after a failed sitemap check, redirects, off-origin links or private URLs.
    if (!report.sitemap.problems.length) {
        let next = 0;
        await Promise.all(Array.from({ length: Math.min(3, urls.length) }, async () => {
            while (next < urls.length) {
                const index = next++;
                report.pages[index] = inspectPage(await get(urls[index]));
            }
        }));
        for (const [field, label] of [['titles', 'Title'], ['descriptions', 'Description']]) {
            const groups = new Map();
            for (const page of report.pages) {
                const value = page[field][0]?.toLowerCase().trim();
                if (value) groups.set(value, [...(groups.get(value) || []), page]);
            }
            for (const group of groups.values()) {
                if (group.length > 1) for (const page of group) page.problems.push(`${label} shared with another sitemap page`);
            }
        }
    }
    report.summary = { sitemapUrls: urls.length, checkedPages: report.pages.length, failedPages: report.pages.filter(p => p.problems.length).length, passed: !report.sitemap.problems.length && !robots.problems.length && report.pages.length === urls.length && report.pages.every(p => !p.problems.length) };
    return report;
}

async function main() {
    const args = process.argv.slice(2);
    const value = args.shift();
    if (!value || args.length && !(args.length === 2 && args[0] === '--output')) throw new Error('Usage: node scripts/check-public-seo.mjs https://clipperdesk.com [--output /path/report.json]');
    const base = new URL(value);
    if (!['http:', 'https:'].includes(base.protocol) || base.username || base.password || base.search || base.hash || base.pathname !== '/') throw new Error('Provide an HTTP(S) origin only, without credentials, query parameters or a path.');
    const report = await checkPublicSeo(base.origin);
    const json = JSON.stringify(report, null, 2) + '\n';
    if (args[0] === '--output') await writeFile(args[1], json);
    process.stdout.write(json);
    if (!report.summary.passed) process.exitCode = 1;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().catch(error => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
}
