import test from 'node:test';
import assert from 'node:assert/strict';
import { inspectPage, inspectRobots, parseSitemap } from '../../scripts/check-public-seo.mjs';

const origin = 'https://clipperdesk.com';
const html = `<html><head><title>Booking software</title><meta name="description" content="Manage your appointments."><meta name="robots" content="index, follow"><link rel="canonical" href="${origin}/"><script type="application/ld+json">{"@type":"WebPage"}</script></head><body><main><h1>Your salon day</h1><p>${'Readable public content. '.repeat(20)}</p><a href="/pricing">Pricing</a></main></body></html>`;
const record = { url: origin + '/', status: 200, contentType: 'text/html', robotsHeader: 'index, follow', body: html };
const sitemap = loc => `<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>${loc}</loc></url></urlset>`;

test('accepts the generated public sitemap and rejects external/private/token URLs before fetching', () => {
    assert.deepEqual(parseSitemap(sitemap(origin + '/help/articles/account-profile'), origin), [origin + '/help/articles/account-profile']);
    // The server's approval/publication gate decides when privacy enters the sitemap.
    assert.deepEqual(parseSitemap(sitemap(origin + '/privacy-policy'), origin), [origin + '/privacy-policy']);
    for (const url of ['https://other.test/', origin + '/book/private', origin + '/pricing?token=secret', origin + '/login', origin + '/help/articles/a#token']) assert.throws(() => parseSitemap(sitemap(url), origin));
    assert.throws(() => parseSitemap('<urlset><url></url></urlset>', origin));
    assert.throws(() => parseSitemap(sitemap(origin + '/').replace('</url>', ''), origin));
});

test('valid robots body with wrong HTTP status fails readiness', () => {
    const robots = { url: origin + '/robots.txt', status: 404, contentType: 'text/plain', body: 'User-agent: *\nAllow: /\nSitemap: ' + origin + '/sitemap.xml\n' };
    assert.ok(inspectRobots(robots, origin).problems.some(p => p.includes('404')));
    assert.deepEqual(inspectRobots({ ...robots, status: 200 }, origin).problems, []);
});

test('requires rendered content and links even if metadata and serialized app data exist', () => {
    assert.deepEqual(inspectPage(record).problems, []);
    const empty = html.replace(/<body>[\s\S]*<\/body>/, '<body><script type="application/json">{"content":"<main><h1>Hidden heading</h1></main>"}</script><div id="app"></div></body>');
    const problems = inspectPage({ ...record, body: empty }).problems;
    assert.ok(problems.includes('Expected one server-rendered H1'));
    assert.ok(problems.includes('No server-rendered links'));
});

test('detects duplicate canonical and conflicting robots directives', () => {
    assert.ok(inspectPage({ ...record, body: html.replace('</head>', `<link href='${origin}/' rel='canonical'></head>`) }).problems.includes('Expected one self-referencing canonical'));
    assert.ok(inspectPage({ ...record, robotsHeader: 'noindex, nofollow' }).problems.some(p => p.includes('restrictive')));
});
