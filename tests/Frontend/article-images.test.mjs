import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';

const vue = pathToFileURL(resolve('node_modules/vue/dist/vue.runtime.esm-bundler.js')).href;
const wrapper = { setup(_props, { slots }) { return () => h('div', slots.default?.()); } };
globalThis.articleImageComponents = {
    HomeLayout: wrapper, PublicContainer: wrapper,
    Breadcrumbs: { render() { return null; } }, Head: { render() { return null; } },
    Link: { props: ['href'], setup(props, { slots }) { return () => h('a', { href: props.href }, slots.default?.()); } },
};
globalThis.route = (_name, slug) => slug ? `/blog/${slug}` : '/blog';

async function load(path) {
    const { descriptor } = parse(await readFile(path, 'utf8'));
    const source = compileScript(descriptor, { id: path, inlineTemplate: true }).content
        .replace(/from (['"])vue\1/g, `from '${vue}'`)
        .replace(/import (\w+) from '@\/[^']+';/g, (_, name) => `const ${name} = globalThis.articleImageComponents.${name};`)
        .replace("import { Head, Link } from '@inertiajs/vue3';", 'const { Head, Link } = globalThis.articleImageComponents;')
        .replace("from '@/Support/marketingVisuals';", `from '${pathToFileURL(resolve('resources/js/Support/marketingVisuals.js')).href}';`);
    return (await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)).default;
}

const Article = await load('resources/js/Pages/Article.vue');
const Blog = await load('resources/js/Pages/Blog.vue');
const article = { id: 1, slug: 'prepare-the-day', title: 'Prepare the day', excerpt: 'A practical guide.', topic: 'Operations', author: 'Editor', published_at: '2026-11-01T12:00:00Z', html: '<h2>Keep the diary ready</h2><p>Article text.</p>', thumbnail: '/storage/articles/featured.png' };

function app(component, props) {
    const instance = createSSRApp(component, props);
    instance.config.globalProperties.route = globalThis.route;
    return instance;
}

test('renders saved featured images on article detail and related cards', async () => {
    const html = await renderToString(app(Article, { article, seo: { title: article.title }, related: [{ ...article, id: 2, thumbnail: '/storage/articles/related.webp' }] }));
    assert.match(html, /src="\/storage\/articles\/featured.png"/);
    assert.match(html, /src="\/storage\/articles\/related.webp"/);
    assert.match(html, /<h2>Keep the diary ready<\/h2>/);
    assert.equal((html.match(/<h1\b/g) || []).length, 1);
});

test('renders the saved card image in the blog and retains an image fallback', async () => {
    const html = await renderToString(app(Blog, { articles: { data: [article, { ...article, id: 2, thumbnail: '' }] }, seo: { title: 'Blog' } }));
    assert.match(html, /src="\/storage\/articles\/featured.png"/);
    assert.ok(!html.includes('src=""'));
    assert.match(html, /href="\/blog\/prepare-the-day"/);
});
