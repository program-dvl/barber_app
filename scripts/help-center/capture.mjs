import puppeteer from 'puppeteer';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

const base = (process.argv[2] || 'http://127.0.0.1:8139').replace(/\/$/, '');
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Only the isolated local fixture may be captured.');
const fixture = JSON.parse(await readFile('tmp/help-center/private-capture.json', 'utf8'));
const output = 'public/images/help';
await mkdir(output, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: process.env.HELP_BROWSER || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--no-sandbox'] });
const manifest = {}, audit = [], errors = [];
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
await page.setRequestInterception(true);
page.on('request', request => { const url = request.url(); if (url.startsWith(base) || url.startsWith('data:') || url.startsWith('blob:')) request.continue(); else request.abort(); });
page.on('pageerror', error => errors.push({ path: new URL(page.url()).pathname, message: error.message }));
const ready = async () => { await page.evaluate(async () => { await document.fonts.ready; }); await page.waitForFunction(() => !document.querySelector('[data-loading="true"]')); };
const go = async path => { const response = await page.goto(`${base}${path}`, { waitUntil: 'networkidle2' }); if (response.status() >= 400) throw new Error(`Page failed ${path}: ${response.status()}`); await ready(); };
const click = async text => { const found = await page.evaluate(text => { const button = [...document.querySelectorAll('button,a,[role=tab]')].find(el => el.innerText?.trim().replace(/\s+/g, ' ').startsWith(text) && el.getBoundingClientRect().width); button?.click(); return Boolean(button); }, text); if (!found) throw new Error(`Control missing: ${text}`); await ready(); };
const save = async (key, path = new URL(page.url()).pathname, fullPage = false) => {
    const geometry = await page.evaluate(() => ({ h1: [...document.querySelectorAll('h1')].map(el => el.innerText), overflow: document.documentElement.scrollWidth > innerWidth + 1, broken: [...document.images].filter(img => img.complete && !img.naturalWidth).map(img => img.getAttribute('src')) }));
    if (!geometry.h1.length) throw new Error(`No application heading for ${key}`);
    const file = `${output}/${key}.webp`;
    await page.screenshot({ path: file, type: 'webp', quality: 84, fullPage });
    const buffer = await readFile(file);
    manifest[key] = { path: `/images/help/${key}.webp`, width: 1440, height: fullPage ? await page.evaluate(() => document.documentElement.scrollHeight) : 1000, captured_at: new Date().toISOString().slice(0, 10), sha256: createHash('sha256').update(buffer).digest('hex') };
    audit.push({ key, path, ...geometry });
    console.log(`Captured ${key}`);
};
const login = async email => {
    await go('/login');
    await page.type('#email', email);
    await page.type('#password', fixture.password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('button[type=submit]')]);
    await ready();
};
const workspace = (slug, path) => `/businesses/${fixture.workspaces[slug]}${path}`;
try {
    await login(fixture.email);
    const tasks = [
        ['dashboard', 'good-hours-demo-tenant', `/dashboard?date=${fixture.fixture_date}`],
        ['calendar', 'good-hours-demo-tenant', `/app/calendar?date=${fixture.fixture_date}`],
        ['walk-in-queue', 'walk-in-workspace-review', '/app/walk-in-queue'],
        ['team-directory', 'team-workspace-review', '/app/team'],
        ['services-catalogue', 'team-workspace-review', '/app/services'],
        ['clients-directory', 'client-workspace-review', '/app/clients'],
        ['checkout', 'checkout-workspace-review', '/app/checkout-sales'],
        ['notifications', 'good-hours-demo-tenant', '/app/communications'],
        ['billing-overview', 'good-hours-demo-tenant', '/billing'],
        ['booking-services', 'public', '/book/service-catalog-review'],
    ];
    for (const [key, slug, path] of tasks) {
        try {
            await go(slug === 'public' ? path : workspace(slug, path));
            await save(key);
            if (key === 'team-directory') { await click('Add team member'); await page.waitForSelector('dialog[open]'); await save('team-add-member'); await page.keyboard.press('Escape'); await click('Weekly coverage'); await save('team-weekly-coverage'); await page.click('.tm-week-cell'); await page.waitForSelector('dialog[open]'); await save('team-schedule'); await page.keyboard.press('Escape'); await click('Access'); await save('team-access'); }
            if (key === 'services-catalogue') { await click('Add service'); await page.waitForSelector('dialog[open]'); await save('services-editor'); await click('Team & locations'); await save('service-assignments'); await click('Booking & rules'); await save('service-booking-rules'); }
            if (key === 'calendar') { await click('Add appointment'); await page.waitForSelector('dialog[open]'); await save('appointment-create'); }
            if (key === 'walk-in-queue') { await click('Add walk-in'); await page.waitForSelector('dialog[open]'); await save('walk-in-add'); }
            if (key === 'clients-directory') { const profile = await page.$('a.crm-client-row'); if (profile) { await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), profile.click()]); await save('client-profile'); } }
            if (key === 'checkout') { await page.click('.co-visit:nth-child(2)'); await page.waitForFunction(() => document.body.innerText.includes('Record payment received')); await save('checkout-payment', undefined, true); }
            if (key === 'notifications') { await click('Delivery history'); await save('notification-history'); }
        } catch (error) { audit.push({ key, missing: error.message }); console.log(`Missing ${key}: ${error.message}`); }
    }
    await go('/user/profile'); await save('account-security');
    // Sign out through the actual account control before using the synthetic setup owner.
    await page.evaluate(() => document.querySelector('details.cd-action-menu:last-of-type')?.setAttribute('open', ''));
    await click('Sign out');
    await page.waitForFunction(() => location.pathname === '/');
    await login('owner@setup.example.test');
    for (const [key, section] of [['setup-readiness', 'overview'], ['business-hours', 'hours'], ['business-booking-preferences', 'booking_rules'], ['business-preview', 'preview']]) {
        try { await go(workspace('business-setup-review-ready', `/configuration?section=${section}`)); await save(key); } catch (error) { audit.push({ key, missing: error.message }); }
    }
    await go(workspace('business-setup-review-ready', '/app/locations')); await save('business-locations');
    await go(workspace('business-setup-review-introduction', '/configuration')); await save('setup-introduction');
    // Reports has its own fixture owner; switching accounts never grants extra access.
    await page.evaluate(() => document.querySelector('details.cd-action-menu:last-of-type')?.setAttribute('open', ''));
    await click('Sign out'); await page.waitForFunction(() => location.pathname === '/');
    await login('reports.owner@example.test');
    await go(workspace('reports-workspace-review', '/app/reports')); await save('reports');
} finally {
    await writeFile('content/help-media.json', JSON.stringify(manifest, null, 2)+'\n');
    await writeFile('docs/audits/2026-10-24-help-center/capture-results.json', JSON.stringify({ audit, errors }, null, 2)+'\n');
    await browser.close();
    if (audit.some(row => row.missing) || errors.length) process.exitCode = 1;
}
