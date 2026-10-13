import { Head, usePage } from '@inertiajs/vue3';
import { defineComponent, h } from 'vue';

// Inertia owns these elements on first render and on every subsequent visit.
const PublicHead = defineComponent({
    setup() {
        const page = usePage();
        return () => {
            const seo = { ...page.props.seoDefaults, ...page.props.seo };
            const meta = (key, content, property = false) => content
                ? h('meta', { 'head-key': key, [property ? 'property' : 'name']: key, content }) : null;
            return h(Head, {}, () => [
                meta('description', seo.description),
                meta('robots', seo.robots),
                seo.canonical ? h('link', { 'head-key': 'canonical', rel: 'canonical', href: seo.canonical }) : null,
                meta('og:title', seo.title, true),
                meta('og:description', seo.description, true),
                meta('og:url', seo.canonical, true),
                meta('og:type', seo.type || 'website', true),
                meta('og:site_name', seo.site_name, true),
                meta('og:locale', seo.locale, true),
                meta('og:image', seo.image, true),
                meta('twitter:title', seo.title),
                meta('twitter:description', seo.description),
                meta('twitter:image', seo.image),
                meta('twitter:card', 'summary_large_image'),
                page.props.structuredData?.['@graph'] ? h('script', {
                    'head-key': 'structured-data', type: 'application/ld+json',
                }, JSON.stringify(page.props.structuredData).replace(/</g, '\\u003c')) : null,
            ].filter(Boolean));
        };
    },
});

// Inertia initializes its head manager while mounting App, before this sibling.
export const renderPublicApp = (App, props) => [h(App, props), h(PublicHead)];
export default PublicHead;
