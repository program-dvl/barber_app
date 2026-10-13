import test from 'node:test';
import assert from 'node:assert/strict';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp, Head } from '@inertiajs/vue3';
import { renderPublicApp } from '../../resources/js/Components/PublicHead.js';

test('renders page metadata and safe JSON-LD through the actual Inertia composition', async () => {
    const page = {
        component: 'TestPage', url: '/features', version: null, clearHistory: false, encryptHistory: false,
        props: {
            seo: { title: 'Features | ClipperDesk', description: 'Booking and daily operations.', canonical: 'https://clipperdesk.com/features', robots: 'index, follow', image: 'https://clipperdesk.com/image.png' },
            structuredData: { '@graph': [{ '@type': 'WebPage', url: 'https://clipperdesk.com/features', description: '</script><script>unsafe</script>' }] },
        },
    };
    const result = await createInertiaApp({
        page, render: renderToString,
        resolve: () => ({ render: () => [h(Head, { title: page.props.seo.title }), h('h1', 'Features')] }),
        setup: ({ App, props, plugin }) => createSSRApp({ render: () => renderPublicApp(App, props) }).use(plugin),
    });
    const head = result.head.join('');
    for (const pattern of [/rel="canonical"/g, /name="description"/g, /name="robots"/g, /<title/g, /type="application\/ld\+json"/g]) {
        assert.equal([...head.matchAll(pattern)].length, 1);
    }
    assert.match(head, /content="https:\/\/clipperdesk.com\/features"/);
    const json = head.match(/<script[^>]+>(.*?)<\/script>/s)[1];
    assert.equal(JSON.parse(json)['@graph'][0].url, page.props.seo.canonical);
    assert.ok(!json.includes('</script>'));
});
