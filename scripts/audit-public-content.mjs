import puppeteer from 'puppeteer';
import { mkdir, writeFile } from 'node:fs/promises';

const base = (process.argv[2] || 'http://127.0.0.1:8013').replace(/\/$/, '');
const live = process.argv.includes('--live');
const output = live ? 'tmp/public-content-audit/live' : 'docs/audits/2026-10-13-public-content/browser';
await mkdir(output, { recursive: true });
const xml = await (await fetch(`${base}/sitemap.xml`)).text();
const paths = [...xml.matchAll(/<loc>(.*?)<\/loc>/g)].map(match => new URL(match[1]).pathname);
const extras = ['/terms-of-service', '/privacy-policy', '/refund-policy', '/login', '/register', '/forgot-password', '/reset-password/audit-token', '/two-factor-challenge', '/book', '/manage-appointment', '/client-forms/completed', '/roadmap', '/changelog', '/coming-soon', '/missing-public-page'];
const uniquePaths = [...new Set([...paths, ...extras])];
const browser = await puppeteer.launch({ headless: true, executablePath: process.env.PUBLIC_AUDIT_BROWSER || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--no-sandbox'] });
const records = [], errors = [];
const forbidden = /\b(?:FR|NFR|ADR|OPEN)-\d+|lorem ipsum|acceptance criteria|implementation notes|development notes|placeholder text|dummy content|Larafast|evidence-rich|keyword-swapped|production domains.*approval/i;
try {
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push({ url: page.url(), message: error.message }));
    for (const path of uniquePaths) {
        for (const width of live ? [1440] : [360, 768, 1440]) {
            await page.setViewport({ width, height: 900 });
            const response = await page.goto(`${base}${path}`, { waitUntil: 'networkidle0' });
            const source = await response.text();
            const server = {
                h1: (source.match(/<h1\b/g) || []).length,
                canonical: (source.match(/rel="canonical"/g) || []).length,
                description: (source.match(/name="description"/g) || []).length,
                schema: (source.match(/type="application\/ld\+json"/g) || []).length,
            };
            await page.evaluate(async () => {
                for (const image of document.images) image.loading = 'eager';
                await Promise.all([...document.images].map(img => img.complete ? Promise.resolve() : new Promise(resolve => { img.onload = resolve; img.onerror = resolve; })));
                await document.fonts.ready;
            });
            const data = await page.evaluate(() => ({
                title: document.title,
                h1: [...document.querySelectorAll('h1')].map(node => node.textContent.trim()),
                mainCount: document.querySelectorAll('main').length,
                overflow: document.documentElement.scrollWidth > innerWidth + 1,
                brokenImages: [...document.images].filter(img => !img.naturalWidth).map(img => img.src),
                canonical: [...document.querySelectorAll('link[rel="canonical"]')].map(node => node.href),
                descriptions: [...document.querySelectorAll('meta[name="description"]')].map(node => node.content),
                robots: [...document.querySelectorAll('meta[name="robots"]')].map(node => node.content),
                ogUrl: [...document.querySelectorAll('meta[property="og:url"]')].map(node => node.content),
                structuredData: [...document.querySelectorAll('script[type="application/ld+json"]')].map(node => JSON.parse(node.textContent)),
                emptyLinks: [...document.querySelectorAll('a')].filter(a => !a.textContent.trim() && !a.getAttribute('aria-label') && !a.querySelector('img[alt]')).map(a => a.href),
                internalLinks: [...new Set([...document.querySelectorAll('a[href]')].filter(a => a.origin === location.origin && !a.hash).map(a => a.pathname))],
                text: document.body.innerText,
                seoText: [...document.querySelectorAll('meta')].map(node => node.content).join(' '),
            }));
            const violations = [];
            if (forbidden.test(data.text + data.seoText + JSON.stringify(data.structuredData))) violations.push('internal copy');
            if (data.h1.length !== 1) violations.push('heading count');
            if (data.mainCount !== 1 && !['/forgot-password'].includes(path)) violations.push('main landmark');
            if (data.overflow) violations.push('horizontal overflow');
            if (data.brokenImages.length) violations.push('broken media');
            if (data.emptyLinks.length) violations.push('empty links');
            if (data.descriptions.length !== 1 || data.robots.length !== 1) violations.push('metadata count');
            if (paths.includes(path) && (data.canonical.length !== 1 || new URL(data.canonical[0]).pathname !== path)) violations.push('canonical');
            if (!live && server.h1 !== 1) violations.push('server-rendered heading');
            const record = { path, width, status: response.status(), server, ...data, violations };
            records.push(record);
            if (!live && width !== 768) await page.screenshot({ path: `${output}/${path.replace(/^\//, '').replaceAll('/', '-') || 'home'}-${width}.jpg`, quality: 82, fullPage: true });
        }
        console.log(`Reviewed ${path}`);
    }
    if (!live) {
        await page.setViewport({ width: 1440, height: 900 });
        await page.goto(`${base}/features`, { waitUntil: 'networkidle0' });
        // Follow the actual Inertia link without reloading and check all managed metadata.
        await page.click('a[href$="/features/online-booking"]');
        await page.waitForFunction(() => location.pathname === '/features/online-booking');
        await page.waitForFunction(() => document.querySelector('link[rel="canonical"]')?.href.endsWith('/features/online-booking'));
        const navigation = await page.evaluate(() => ({
            path: location.pathname, title: document.title,
            canonicalCount: document.querySelectorAll('link[rel="canonical"]').length,
            descriptionCount: document.querySelectorAll('meta[name="description"]').length,
            ogUrl: document.querySelector('meta[property="og:url"]')?.content,
            schemaUrl: JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)['@graph'].find(node => node['@type'] === 'WebPage').url,
        }));
        await writeFile(`${output}/navigation.json`, JSON.stringify(navigation, null, 2));
        await page.goto(`${base}/`, { waitUntil: 'networkidle0' });
        await page.click('a[href$="/register"]');
        await page.waitForFunction(() => location.pathname === '/register');
        await page.waitForFunction(() => document.querySelector('meta[name="robots"]')?.content.startsWith('noindex'));
        await writeFile(`${output}/auth-navigation.json`, JSON.stringify(await page.evaluate(() => ({
            path: location.pathname, robots: document.querySelector('meta[name="robots"]').content,
            schemaCount: document.querySelectorAll('script[type="application/ld+json"]').length,
            canonicalCount: document.querySelectorAll('link[rel="canonical"]').length,
        })), null, 2));
    }
} finally {
    await browser.close();
    await writeFile(`${output}/results.json`, JSON.stringify({ base, sitemapCount: paths.length, records, errors }, null, 2));
    console.log(JSON.stringify({ visits: records.length, violations: records.filter(row => row.violations.length).map(row => ({ path: row.path, width: row.width, violations: row.violations })), errors }, null, 2));
}
if (errors.length || (!live && records.some(row => row.violations.length))) process.exitCode = 1;
